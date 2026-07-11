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
import { badRequest, unauthorized, tooMany } from '../utils/respond.js';
import logger from '../utils/logger.js';

// ── Brute-force store (PHP kept this in the session; single-server parity) ──
const loginAttempts = new Map(); // key: loginId → { count, locked_until (epoch secs) }

function getFailedAttempts(id) {
  let data = loginAttempts.get(id) || { count: 0, locked_until: 0 };
  if (data.locked_until > 0 && Math.floor(Date.now() / 1000) > data.locked_until) {
    data = { count: 0, locked_until: 0 };
    loginAttempts.delete(id);
  }
  return data;
}

function recordFailedAttempt(id) {
  const data = getFailedAttempts(id);
  data.count += 1;
  if (data.count >= 5) data.locked_until = Math.floor(Date.now() / 1000) + 900; // 15 min
  loginAttempts.set(id, data);
}

function clearFailedAttempts(id) {
  loginAttempts.delete(id);
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
  password = String(password || '').trim();
  const cleanSlug = sanitizeSlug(orgSlug);

  if (!email || !password) throw badRequest('Email and password are required.');

  const loginId = `${email.toLowerCase()}|${cleanSlug || 'default'}`;

  // Lockout check
  const attempts = getFailedAttempts(loginId);
  const now = Math.floor(Date.now() / 1000);
  if (attempts.locked_until > 0 && now < attempts.locked_until) {
    const waitSecs = attempts.locked_until - now;
    throw tooMany(
      `Too many failed attempts. Please try again in ${Math.ceil(waitSecs / 60)} minute(s).`,
      { retry_after: waitSecs }
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
      clearFailedAttempts(loginId);
      const token = signToken({ user: session, platform_admin: true });
      return { user: session, token };
    }
  } catch (e) {
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
    recordFailedAttempt(loginId);
    const remaining = Math.max(0, 5 - getFailedAttempts(loginId).count);
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

  clearFailedAttempts(loginId);
  const token = signToken({ user: session, org_slug: tenant.slug });
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

  // Invalidate existing tokens, then issue a new bcrypt-hashed token (1h TTL).
  await db.execute('DELETE FROM password_reset_tokens WHERE user_id = ?', [user.id]);

  const token = crypto.randomBytes(32).toString('hex');
  const hashedToken = bcrypt.hashSync(token, 10);
  const expiresAt = new Date(Date.now() + 3600 * 1000)
    .toISOString()
    .slice(0, 19)
    .replace('T', ' ');

  await db.execute(
    'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
    [user.id, hashedToken, expiresAt]
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
  token = String(token || '').trim();
  password = String(password || '').trim();
  const cleanSlug = sanitizeSlug(orgSlug);

  if (!token || !password) throw badRequest('Token and new password are required.');
  if (password.length < 8) throw badRequest('Password must be at least 8 characters.');

  const tenant = await resolveTenant({ slug: cleanSlug, host });
  const db = getTenantPool(tenant);

  const [tokens] = await db.query(
    'SELECT id, user_id, token_hash FROM password_reset_tokens WHERE expires_at > NOW()'
  );
  let matched = null;
  for (const t of tokens) {
    if (bcrypt.compareSync(token, t.token_hash)) {
      matched = t;
      break;
    }
  }
  if (!matched) {
    throw badRequest('Invalid or expired reset link. Please request a new one.');
  }

  const hash = bcrypt.hashSync(password, 10);
  await db.execute('UPDATE users SET password_hash = ? WHERE id = ?', [hash, matched.user_id]);
  await db.execute('DELETE FROM password_reset_tokens WHERE id = ?', [matched.id]);

  return { success: true, message: 'Password updated successfully. Please log in with your new password.' };
}

/** Check whether a reset token is still valid. */
export async function checkResetToken({ token, orgSlug, host }) {
  token = String(token || '').trim();
  if (!token) throw badRequest('Token required.');
  const cleanSlug = sanitizeSlug(orgSlug);

  const tenant = await resolveTenant({ slug: cleanSlug, host });
  const db = getTenantPool(tenant);

  const [tokens] = await db.query(
    'SELECT token_hash FROM password_reset_tokens WHERE expires_at > NOW()'
  );
  let valid = false;
  for (const t of tokens) {
    if (bcrypt.compareSync(token, t.token_hash)) {
      valid = true;
      break;
    }
  }
  return { success: true, valid };
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])
  );
}

export default { login, forgotPassword, resetPassword, checkResetToken };
