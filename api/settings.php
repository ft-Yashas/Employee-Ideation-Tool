<?php
// api/settings.php  –  Organisation settings management
// GET  ?action=get              — fetch all org settings (any authenticated user)
// POST ?action=update           — update whitelisted settings (admin/super_admin)
// GET  ?action=send_test_email  — send a test email via configured SMTP (admin/super_admin)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

$user   = requireAuth();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$pdo    = db();

// ── Whitelist of allowed setting keys ────────────────────────────
const SETTINGS_WHITELIST = [
    'review_sla_days',
    'escalation_days',
    'anonymous_allowed',
    'public_board_enabled',
    'challenges_enabled',
    'email_enabled',
    'smtp_host',
    'smtp_port',
    'smtp_user',
    'smtp_pass',
    'smtp_from',
    'smtp_from_name',
    'approval_mode',
    'approval_reviewer_roles',
    'approval_final_approver_roles',
    'approval_threshold',
];

const VALID_ROLES = [
    'trainee','employee','team_lead','project_lead',
    'manager','senior_manager','executive','admin','super_admin'
];

// ── GET all settings ──────────────────────────────────────────────
if ($action === 'get' && $method === 'GET') {
    $settings = getOrgSettings();

    // Mask SMTP password for non-super-admins
    if (!in_array($user['role'], ['admin', 'super_admin'], true)) {
        unset($settings['smtp_pass']);
    } else {
        // Replace actual password with a masked placeholder for display
        // (client should send back the same placeholder to avoid clearing it)
        if (!empty($settings['smtp_pass'])) {
            $settings['smtp_pass_set'] = true;
            $settings['smtp_pass']     = '••••••••';
        } else {
            $settings['smtp_pass_set'] = false;
        }
    }

    respond(['success' => true, 'settings' => $settings]);
}

// ── UPDATE settings ───────────────────────────────────────────────
if ($action === 'update' && $method === 'POST') {
    requireCsrf();
    requireRole('admin', 'super_admin');
    $b = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!is_array($b) || empty($b)) {
        respond(['success' => false, 'error' => 'No settings provided.'], 400);
    }

    $updateStmt = $pdo->prepare(
        "INSERT INTO org_settings (key_name, value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)"
    );

    $updated = 0;
    foreach ($b as $key => $value) {
        if (!in_array($key, SETTINGS_WHITELIST, true)) {
            continue; // silently skip unknown keys
        }

        // If smtp_pass is the masked placeholder, skip (don't overwrite)
        if ($key === 'smtp_pass' && $value === '••••••••') {
            continue;
        }

        // ── Approval workflow validation ────────────────────────────
        if ($key === 'approval_mode' && !in_array($value, ['default','custom'], true)) {
            continue; // reject invalid mode
        }
        if ($key === 'approval_threshold') {
            $v = max(1, min(100, (int)$value));
            $value = (string)$v;
        }
        if (in_array($key, ['approval_reviewer_roles','approval_final_approver_roles'], true)) {
            $parts = array_filter(array_map('trim', explode(',', $value)));
            $value = implode(',', $parts ?: []);
        }

        $updateStmt->execute([$key, (string)$value]);
        $updated++;
    }

    respond(['success' => true, 'updated' => $updated]);
}

// ── SEND TEST EMAIL ───────────────────────────────────────────────
if ($action === 'send_test_email' && $method === 'GET') {
    requireRole('admin', 'super_admin');

    $settings = getOrgSettings();

    $smtpHost = trim($settings['smtp_host'] ?? '');
    if ($smtpHost === '') {
        respond(['success' => false, 'error' => 'SMTP host is not configured. Please save SMTP settings first.'], 400);
    }

    // Fetch the requesting user's email
    $uStmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$user['id']]);
    $uRow = $uStmt->fetch();

    $toEmail = $uRow['email'] ?? ($user['email'] ?? '');
    $toName  = $uRow['name']  ?? ($user['name']  ?? 'Admin');

    if (!$toEmail || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        respond(['success' => false, 'error' => 'Your account does not have a valid email address.'], 400);
    }

    $subject = 'IFQM Ideation – Test Email';
    $body    = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;padding:20px;color:#1e293b">'
             . '<h2 style="color:#4f46e5">IFQM Ideation Tool – Test Email</h2>'
             . '<p>Hi ' . htmlspecialchars($toName) . ',</p>'
             . '<p>This is a test email confirming that your SMTP configuration is working correctly.</p>'
             . '<p style="color:#64748b;font-size:12px">Sent at ' . date('Y-m-d H:i:s') . ' (server time)</p>'
             . '</body></html>';

    try {
        $sent = sendSmtpEmail($settings, $toEmail, $toName, $subject, $body);
        if ($sent) {
            respond(['success' => true, 'message' => 'Test email sent to ' . $toEmail]);
        } else {
            respond(['success' => false, 'error' => 'Failed to send test email. Check SMTP settings.']);
        }
    } catch (Exception $e) {
        respond(['success' => false, 'error' => 'SMTP error: ' . $e->getMessage()]);
    }
}

respond(['success' => false, 'error' => 'Unknown action'], 400);
