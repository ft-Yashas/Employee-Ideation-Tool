/**
 * Authentication service — Node equivalent of PHP api/auth.php.
 *
 * Reproduces, identically:
 *   • brute-force lockout (5 fails → 15-min lock, per email|org identifier)
 *   • platform-admin-first login (ifqm_master.platform_admins), then tenant user
 *   • bcrypt password verification (PHP $2y$ hashes verify unchanged)
 *   • forgot / reset / check-reset-token password flows (bcrypt-hashed tokens)
 *
 * Differences (mandated by the session→JWT migration, documented):
 *   • The PHP session is replaced by a signed JWT returned as `token`.
 *   • CSRF tokens (a session artifact) are dropped — JWT travels in the
 *     Authorization header, so there is no ambient credential to forge.
 */
import bcrypt from 'bcryptjs';
import crypto from 'node:crypto';
import config from '../config/index.js';
import { signToken } from '../utils/jwt.js';
import { masterDb } from '../database/master.js';
import { resolveTenant, getTenantPool, sanitizeSlug } from '../database/tenant.js';
import { getOrgSettings, sendSmtpEmail } from './mailerService.js';
import { badRequest, unauthorized, tooMany, ApiError } from '../utils/respond.js';
import logger from '../utils/logger.js';

// ── Brute-force lockout ──────────────────────────────────────────────────────
// Persisted in ifqm_master.login_attempts. This used to be a process-local Map,
// which meant the lockout reset on every restart or deploy (wait for a bounce
// and keep guessing), did not exist across a second worker, and grew without
// bound. Keyed per <email>|<org> so one account locks — not everyone sharing an
// office IP.
const MAX_ATTEMPTS = 5;
const LOCKOUT_SECONDS = 900; // 15 min

// If the master DB is unreachable we still must not let guessing run free, so
// fall back to an in-process counter for the life of the process.
const memoryAttempts = new Map();

async function getFailedAttempts(id) {
  try {
    const [rows] = await masterDb().execute(
      `SELECT attempts,
              GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS locked_for
         FROM login_attempts WHERE login_id = ? LIMIT 1`,
      [id]
    );
    const row = rows[0];
    if (!row) return { count: 0, locked_for: 0 };
    return { count: Number(row.attempts) || 0, locked_for: Number(row.locked_for) || 0 };
  } catch {
    const m = memoryAttempts.get(id) || { count: 0, locked_until: 0 };
    const left = m.locked_until - Math.floor(Date.now() / 1000);
    return { count: m.count, locked_for: Math.max(0, left) };
  }
}

async function recordFailedAttempt(id) {
  try {
    // NOTE: inside ON DUPLICATE KEY UPDATE, MySQL evaluates the assignments in
    // order, so `attempts` in the second expression is the value ALREADY
    // incremented by the first. Comparing `attempts + 1` here would therefore
    // count one too many and lock the account on the 4th failure while the user
    // was still being told "1 attempt remaining".
    await masterDb().execute(
      `INSERT INTO login_attempts (login_id, attempts, locked_until)
            VALUES (?, 1, NULL)
       ON DUPLICATE KEY UPDATE
            attempts     = attempts + 1,
            locked_until = IF(attempts >= ?, DATE_ADD(NOW(), INTERVAL ? SECOND), locked_until)`,
      [id, MAX_ATTEMPTS, LOCKOUT_SECONDS]
    );
  } catch {
    const m = memoryAttempts.get(id) || { count: 0, locked_until: 0 };
    m.count += 1;
    if (m.count >= MAX_ATTEMPTS) m.locked_until = Math.floor(Date.now() / 1000) + LOCKOUT_SECONDS;
    memoryAttempts.set(id, m);
  }
}

async function clearFailedAttempts(id) {
  try {
    await masterDb().execute('DELETE FROM login_attempts WHERE login_id = ?', [id]);
  } catch { /* best effort */ }
  memoryAttempts.delete(id);
}

/** First-letters-of-first-two-words initials (PHP array_map over name words). */
function initialsFrom(name) {
  return (
    String(name || '')
      .split(' ')
      .slice(0, 2)
      .map((w) => (w ? w[0].toUpperCase() : ''))
      .join('') || ''
  );
}

/**
 * Authenticate a user (platform admin or tenant user).
 * @returns {Promise<{ user: object, token: string }>}
 */
export async function login({ email, password, orgSlug, host }) {
  email = String(email || '').trim();
  // Deliberately NOT trimmed: the password must be compared exactly as the user
  // set it. Trimming here while storing it untrimmed would silently lock out
  // anyone whose password begins or ends with a space.
  password = String(password ?? '');
  const cleanSlug = sanitizeSlug(orgSlug);

  if (!email || !password) throw badRequest('Email and password are required.');

  const loginId = `${email.toLowerCase()}|${cleanSlug || 'default'}`;

  // Lockout check
  const attempts = await getFailedAttempts(loginId);
  if (attempts.locked_for > 0) {
    throw tooMany(
      `Too many failed attempts. Please try again in ${Math.ceil(attempts.locked_for / 60)} minute(s).`,
      { retry_after: attempts.locked_for }
    );
  }

  // ── Try platform admin first (ifqm_master.platform_admins) ──
  try {
    const master = masterDb();
    const [rows] = await master.execute(
      'SELECT * FROM platform_admins WHERE email = ? LIMIT 1',
      [email]
    );
    const pa = rows[0];
    if (pa && bcrypt.compareSync(password, pa.password_hash)) {
      const session = {
        id: `pa_${pa.id}`,
        name: pa.name,
        email: pa.email,
        role: 'platform_admin',
        avatar_initials: initialsFrom(pa.name) || 'PA',
        points: 0,
      };
      await clearFailedAttempts(loginId);
      const token = signToken({ user: session, platform_admin: true });
      logger.info(`auth: platform admin login ok (${email})`);
      return { user: session, token };
    }
  } catch (e) {
    if (e instanceof ApiError) throw e;
    // ifqm_master unavailable — fall through to tenant auth (as PHP did)
    logger.warn('platform_admins lookup skipped', e.message);
  }

  // ── Tenant user auth ──
  const tenant = await resolveTenant({ slug: cleanSlug, host });
  const db = getTenantPool(tenant);

  const [rows] = await db.execute(
    `SELECT u.*, m.name AS manager_name
       FROM users u
       LEFT JOIN users m ON m.id = u.manager_id
      WHERE u.email = ? AND u.status = 'active' LIMIT 1`,
    [email]
  );
  const user = rows[0];

  if (!user || !bcrypt.compareSync(password, user.password_hash)) {
    await recordFailedAttempt(loginId);
    const after = await getFailedAttempts(loginId);
    const remaining = Math.max(0, MAX_ATTEMPTS - after.count);
    logger.warn(`auth: failed login for ${loginId} (${after.count}/${MAX_ATTEMPTS})`);
    const err =
      remaining > 0
        ? `Invalid email, password, or organization code. ${remaining} attempt(s) remaining.`
        : 'Too many failed attempts. Please try again in 15 minutes.';
    throw unauthorized(err);
  }

  const session = {
    id: user.id,
    employee_id: user.employee_id,
    name: user.name,
    email: user.email,
    phone: user.phone,
    department: user.department,
    business_unit: user.business_unit,
    location: user.location,
    role: user.role,
    manager_id: user.manager_id,
    manager_name: user.manager_name,
    points: user.points,
    avatar_initials: user.avatar_initials || (user.name || '').charAt(0).toUpperCase(),
    org_name: tenant.name,
    org_slug: tenant.slug,
  };

  await clearFailedAttempts(loginId);
  const token = signToken({ user: session, org_slug: tenant.slug });
  logger.info(`auth: login ok (${email} @ ${tenant.slug})`);
  return { user: session, token };
}

/**
 * Forgot-password: always returns a generic success (anti-enumeration).
 * Emails a reset link only when SMTP is enabled/configured.
 */
export async function forgotPassword({ email, orgSlug, host }) {
  email = String(email || '').trim().toLowerCase();
  const cleanSlug = sanitizeSlug(orgSlug);
  if (!email) throw badRequest('Email is required.');

  const generic = {
    success: true,
    message: 'If an account with that email exists, a reset link has been sent.',
  };

  const tenant = await resolveTenant({ slug: cleanSlug, host });
  const db = getTenantPool(tenant);

  const [rows] = await db.execute(
    "SELECT id, name FROM users WHERE email = ? AND status = 'active' LIMIT 1",
    [email]
  );
  const user = rows[0];
  if (!user) return generic;

  // Invalidate existing tokens, then issue a new one (1h TTL).
  await db.execute('DELETE FROM password_reset_tokens WHERE user_id = ?', [user.id]);

  // Split token: <selector>.<verifier>. The selector is an indexed lookup so we
  // run exactly ONE bcrypt compare. Previously verification bcrypt-compared the
  // candidate against every unexpired row in the table, which let anyone burn
  // arbitrary CPU by posting junk tokens.
  const { token, selector, verifierHash } = makeResetToken();
  const expiresAt = new Date(Date.now() + 3600 * 1000)
    .toISOString()
    .slice(0, 19)
    .replace('T', ' ');

  await db.execute(
    'INSERT INTO password_reset_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)',
    [user.id, selector, verifierHash, expiresAt]
  );

  // Send the email if enabled/configured (best-effort; never leaks failure).
  try {
    const settings = await getOrgSettings(db);
    if (settings.email_enabled === '1' && String(settings.smtp_host || '').trim()) {
      const base = config.frontendBaseUrl.replace(/\/+$/, '');
      const resetUrl = `${base}/reset-password?token=${encodeURIComponent(token)}${
        cleanSlug ? `&org=${cleanSlug}` : ''
      }`;
      const subject = 'Reset Your IFQM Password';
      const htmlBody =
        '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>' +
        '<body style="font-family:Arial,sans-serif;padding:20px;color:#1e293b">' +
        '<h2 style="color:#4f46e5">IFQM – Password Reset Request</h2>' +
        `<p>Hi ${escapeHtml(user.name)},</p>` +
        '<p>We received a request to reset your IFQM account password. Click the button below to set a new password. This link expires in 1 hour.</p>' +
        `<p><a href="${escapeHtml(resetUrl)}" style="display:inline-block;background:#4f46e5;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold;">Reset Password</a></p>` +
        '<p style="color:#64748b;font-size:12px">If you did not request this, you can safely ignore this email. The link will expire automatically.</p>' +
        '</body></html>';
      await sendSmtpEmail(settings, email, user.name, subject, htmlBody);
    }
  } catch (e) {
    logger.error('Password reset email error', e.message);
  }

  return generic;
}

/** Reset password given a valid, unexpired token. */
export async function resetPassword({ token, password, orgSlug, host }) {
  token = String(token || '');
  password = String(password || '');
  const cleanSlug = sanitizeSlug(orgSlug);

  if (!token || !password) throw badRequest('Token and new password are required.');
  assertPasswordStrength(password);

  const tenant = await resolveTenant({ slug: cleanSlug, host });
  const db = getTenantPool(tenant);

  const matched = await findResetToken(db, token);
  if (!matched) throw badRequest('Invalid or expired reset link. Please request a new one.');

  const hash = bcrypt.hashSync(password, BCRYPT_ROUNDS);

  // Stamping password_changed_at is what actually kills the old sessions: the
  // auth middleware rejects any JWT issued before this moment. Without it, a
  // stolen token stayed usable for the rest of its 8-hour life even after the
  // victim reset their password.
  await db.execute(
    'UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?',
    [hash, matched.user_id]
  );
  // Burn every outstanding reset token for this user, not just the one used.
  await db.execute('DELETE FROM password_reset_tokens WHERE user_id = ?', [matched.user_id]);

  logger.info(`auth: password reset completed for user ${matched.user_id} @ ${tenant.slug}`);
  return { success: true, message: 'Password updated successfully. Please log in with your new password.' };
}

/** Check whether a reset token is still valid. */
export async function checkResetToken({ token, orgSlug, host }) {
  token = String(token || '');
  if (!token) throw badRequest('Token required.');
  const cleanSlug = sanitizeSlug(orgSlug);

  const tenant = await resolveTenant({ slug: cleanSlug, host });
  const db = getTenantPool(tenant);

  const matched = await findResetToken(db, token);
  return { success: true, valid: !!matched };
}

// ── Reset-token helpers ─────────────────────────────────────────────────────
const BCRYPT_ROUNDS = 12; // ~250ms; was 10

/** Mint a `<selector>.<verifier>` reset token. Only the verifier is hashed. */
function makeResetToken() {
  const selector = crypto.randomBytes(16).toString('hex'); // 32 chars, indexed
  const verifier = crypto.randomBytes(32).toString('hex'); // the actual secret
  return {
    token: `${selector}.${verifier}`,
    selector,
    verifierHash: bcrypt.hashSync(verifier, BCRYPT_ROUNDS),
  };
}

/** Look a token up by selector (one indexed row → one bcrypt compare). */
async function findResetToken(db, token) {
  const dot = token.indexOf('.');
  if (dot < 1) return null;
  const selector = token.slice(0, dot);
  const verifier = token.slice(dot + 1);
  if (!/^[a-f0-9]{32}$/.test(selector) || !verifier) return null;

  const [rows] = await db.execute(
    'SELECT id, user_id, token_hash FROM password_reset_tokens WHERE selector = ? AND expires_at > NOW() LIMIT 1',
    [selector]
  );
  const row = rows[0];
  if (!row) return null;
  return bcrypt.compareSync(verifier, row.token_hash) ? row : null;
}

/**
 * One password policy for every path that sets a password (self-service reset,
 * admin-created accounts, new tenant admins). Length is the control that
 * actually matters (NIST SP 800-63B); we additionally reject the handful of
 * passwords that show up first in every credential-stuffing list.
 */
const WORST_PASSWORDS = new Set([
  'password', 'password1', 'password123', '12345678', '123456789', '1234567890',
  'qwertyuiop', 'letmein123', 'welcome123', 'admin123', 'iloveyou', 'changeme',
  'ifqm1234', 'ifqm@1234', 'passw0rd', 'p@ssw0rd', 'administrator',
]);

export function assertPasswordStrength(password, { label = 'Password' } = {}) {
  const pw = String(password ?? '');
  const min = config.minPasswordLength;

  if (pw.length < min) throw badRequest(`${label} must be at least ${min} characters.`);
  if (pw.length > 200) throw badRequest(`${label} is too long (max 200 characters).`);
  if (WORST_PASSWORDS.has(pw.toLowerCase())) {
    throw badRequest(`${label} is one of the most commonly guessed passwords. Choose another.`);
  }
  if (/^(.)\1+$/.test(pw)) throw badRequest(`${label} cannot be a single repeated character.`);
  return pw;
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])
  );
}

export default { login, forgotPassword, resetPassword, checkResetToken, assertPasswordStrength };
