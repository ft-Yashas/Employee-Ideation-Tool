/**
 * Org-settings service — Node port of PHP api/settings.php, plus
 * getApprovalConfig() (used by the ideas workflow, mirrors ideas.php).
 *
 * NOTE: PHP memoised getOrgSettings with a request-scoped `static`. Here we read
 * fresh each call (no cross-request/tenant caching), the safe equivalent.
 */
import { getOrgSettings, sendSmtpEmail } from './mailerService.js';
import { badRequest, ApiError } from '../utils/respond.js';

const SETTINGS_WHITELIST = [
  'review_sla_days', 'escalation_days', 'anonymous_allowed', 'public_board_enabled',
  'challenges_enabled', 'email_enabled', 'smtp_host', 'smtp_port', 'smtp_user',
  'smtp_pass', 'smtp_from', 'smtp_from_name', 'approval_mode',
  'approval_reviewer_roles', 'approval_final_approver_roles', 'approval_threshold',
];

const SMTP_PASS_MASK = '••••••••';
const isAdmin = (role) => role === 'admin' || role === 'super_admin';

const DEFAULT_REVIEWER_ROLES = ['team_lead', 'project_lead', 'manager', 'senior_manager'];
const DEFAULT_FINAL_ROLES = ['executive', 'admin', 'super_admin'];

// Roles a tenant admin may place in their approval chain. Anything else typed
// into the role lists is dropped on save — a bad role name would silently
// exclude reviewers from the escalation walk.
const VALID_CHAIN_ROLES = [
  'team_lead', 'project_lead', 'manager', 'senior_manager', 'executive', 'admin', 'super_admin',
];

export async function getApprovalConfig(db) {
  const settings = await getOrgSettings(db);
  const mode = settings.approval_mode ?? 'default';

  if (mode !== 'custom') {
    return {
      mode: 'default',
      reviewer_roles: [...DEFAULT_REVIEWER_ROLES],
      final_roles: [...DEFAULT_FINAL_ROLES],
      threshold: parseInt(settings.approval_threshold ?? '100', 10) || 100,
    };
  }

  const parseRoles = (v) => String(v ?? '').split(',').map((s) => s.trim()).filter(Boolean);
  let reviewerRoles = parseRoles(settings.approval_reviewer_roles);
  let finalRoles = parseRoles(settings.approval_final_approver_roles);
  if (!reviewerRoles.length) reviewerRoles = [...DEFAULT_REVIEWER_ROLES];
  if (!finalRoles.length) finalRoles = [...DEFAULT_FINAL_ROLES];

  const rawThreshold = parseInt(settings.approval_threshold ?? '100', 10) || 100;
  return {
    mode: 'custom',
    reviewer_roles: reviewerRoles,
    final_roles: finalRoles,
    threshold: Math.max(1, Math.min(100, rawThreshold)),
  };
}

// ── GET all settings (with SMTP-password masking) ──────────────────
export async function getSettings(db, user) {
  const settings = await getOrgSettings(db);

  if (!isAdmin(user.role)) {
    delete settings.smtp_pass;
  } else if (settings.smtp_pass) {
    settings.smtp_pass_set = true;
    settings.smtp_pass = SMTP_PASS_MASK;
  } else {
    settings.smtp_pass_set = false;
  }

  return { success: true, settings };
}

// ── UPDATE whitelisted settings ────────────────────────────────────
export async function updateSettings(db, body) {
  if (!body || typeof body !== 'object' || Array.isArray(body) || Object.keys(body).length === 0) {
    throw badRequest('No settings provided.');
  }

  let updated = 0;
  for (const [key, rawValue] of Object.entries(body)) {
    if (!SETTINGS_WHITELIST.includes(key)) continue;               // skip unknown keys
    if (key === 'smtp_pass' && rawValue === SMTP_PASS_MASK) continue; // keep existing password

    let value = rawValue;
    if (key === 'approval_mode' && !['default', 'custom'].includes(value)) continue; // reject invalid mode
    if (key === 'approval_threshold') {
      value = String(Math.max(1, Math.min(100, parseInt(value, 10) || 0)));
    }
    if (key === 'approval_reviewer_roles' || key === 'approval_final_approver_roles') {
      value = String(value).split(',').map((s) => s.trim())
        .filter((r) => VALID_CHAIN_ROLES.includes(r)).join(',');
    }

    await db.execute(
      `INSERT INTO org_settings (key_name, value) VALUES (?, ?)
       ON DUPLICATE KEY UPDATE value = VALUES(value)`,
      [key, String(value)]
    );
    updated++;
  }

  return { success: true, updated };
}

// ── SEND TEST EMAIL ────────────────────────────────────────────────
export async function sendTestEmail(db, user) {
  const settings = await getOrgSettings(db);
  if (!String(settings.smtp_host || '').trim()) {
    throw badRequest('SMTP host is not configured. Please save SMTP settings first.');
  }

  const [rows] = await db.execute('SELECT email, name FROM users WHERE id = ? LIMIT 1', [user.id]);
  const toEmail = rows[0]?.email || user.email || '';
  const toName = rows[0]?.name || user.name || 'Admin';

  if (!toEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(toEmail)) {
    throw badRequest('Your account does not have a valid email address.');
  }

  const now = new Date();
  const p2 = (n) => String(n).padStart(2, '0');
  const stamp = `${now.getFullYear()}-${p2(now.getMonth() + 1)}-${p2(now.getDate())} ${p2(now.getHours())}:${p2(now.getMinutes())}:${p2(now.getSeconds())}`;
  const subject = 'IFQM Ideation – Test Email';
  const body =
    '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>' +
    '<body style="font-family:Arial,sans-serif;padding:20px;color:#1e293b">' +
    '<h2 style="color:#4f46e5">IFQM Ideation Tool – Test Email</h2>' +
    `<p>Hi ${escapeHtml(toName)},</p>` +
    '<p>This is a test email confirming that your SMTP configuration is working correctly.</p>' +
    `<p style="color:#64748b;font-size:12px">Sent at ${stamp} (server time)</p>` +
    '</body></html>';

  try {
    const sent = await sendSmtpEmail(settings, toEmail, toName, subject, body);
    if (sent) return { success: true, message: 'Test email sent to ' + toEmail };
    return { success: false, error: 'Failed to send test email. Check SMTP settings.' };
  } catch (e) {
    // PHP returned HTTP 200 with success:false for SMTP errors here.
    throw new ApiError(200, 'SMTP error: ' + e.message);
  }
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])
  );
}

export default { getApprovalConfig, getSettings, updateSettings, sendTestEmail };
