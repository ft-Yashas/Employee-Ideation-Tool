<?php
require_once __DIR__ . '/config.php';

session_set_cookie_params(SESSION_LIFETIME);
session_start();

// ── Brute-force protection: track failed attempts in memory ─────
function getFailedAttempts(string $identifier): array {
    $key = 'failed_' . md5($identifier);
    $data = $_SESSION[$key] ?? ['count' => 0, 'locked_until' => 0];
    // Reset if lockout has expired
    if ($data['locked_until'] > 0 && time() > $data['locked_until']) {
        $data = ['count' => 0, 'locked_until' => 0];
        unset($_SESSION[$key]);
    }
    return $data;
}

function recordFailedAttempt(string $identifier): void {
    $key = 'failed_' . md5($identifier);
    $data = getFailedAttempts($identifier);
    $data['count']++;
    // Lock for 15 minutes after 5 consecutive failures
    if ($data['count'] >= 5) {
        $data['locked_until'] = time() + 900;
    }
    $_SESSION[$key] = $data;
}

function clearFailedAttempts(string $identifier): void {
    $key = 'failed_' . md5($identifier);
    unset($_SESSION[$key]);
}

$action = $_GET['action'] ?? 'me';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'me') {
    if (empty($_SESSION['user_id'])) {
        respond(['success' => false, 'authenticated' => false]);
    }
    // Return CSRF token with session info
    respond([
        'success'    => true,
        'authenticated' => true,
        'user'        => $_SESSION['user'],
        'csrf_token'  => generateCsrfToken(),
    ]);
}

if ($action === 'login') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $email   = trim($body['email'] ?? '');
    $pass    = trim($body['password'] ?? '');
    $orgSlug = strtolower(preg_replace('/[^a-z0-9_-]/', '', trim($body['org_slug'] ?? '')));

    if (!$email || !$pass) {
        respond(['success' => false, 'error' => 'Email and password are required.'], 400);
    }

    // ── Brute-force check: lock out after 5 failed attempts ──────
    $loginId = strtolower($email) . '|' . ($orgSlug ?: 'default');
    $attempts = getFailedAttempts($loginId);
    if ($attempts['locked_until'] > 0 && time() < $attempts['locked_until']) {
        $waitSecs = $attempts['locked_until'] - time();
        respond([
            'success' => false,
            'error'   => "Too many failed attempts. Please try again in " . ceil($waitSecs / 60) . " minute(s).",
            'retry_after' => $waitSecs,
        ], 429);
    }

    // ── Try platform admin first (ifqm_master.platform_admins) ────────────
    try {
        $master = masterDb();
        $paStmt = $master->prepare("SELECT * FROM platform_admins WHERE email = ? LIMIT 1");
        $paStmt->execute([$email]);
        $pa = $paStmt->fetch();
        if ($pa && password_verify($pass, $pa['password_hash'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $pa['name']), 0, 2)));
            $session = [
                'id'              => 'pa_' . $pa['id'],
                'name'            => $pa['name'],
                'email'           => $pa['email'],
                'role'            => 'platform_admin',
                'avatar_initials' => $initials ?: 'PA',
                'points'          => 0,
            ];
            $_SESSION['platform_admin'] = true;
            $_SESSION['user_id']        = $session['id'];
            $_SESSION['user']           = $session;
            $_SESSION['last_activity']  = time();
            $_SESSION['csrf_token']     = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
            unset($_SESSION['org_slug']);
            clearFailedAttempts($loginId);
            respond(['success' => true, 'user' => $session]);
        }
    } catch (Exception $e) {
        // ifqm_master unavailable — fall through to tenant auth
    }

    // ── Tenant user auth ───────────────────────────────────────────────────
    // Inject org slug so resolveTenant() picks up the right DB
    if ($orgSlug) $_GET['org'] = $orgSlug;

    $stmt = db()->prepare(
        "SELECT u.*, m.name AS manager_name
         FROM users u
         LEFT JOIN users m ON m.id = u.manager_id
         WHERE u.email = ? AND u.status = 'active' LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        recordFailedAttempt($loginId);
        $remaining = max(0, 5 - getFailedAttempts($loginId)['count']);
        $err = $remaining > 0
            ? "Invalid email, password, or organization code. {$remaining} attempt(s) remaining."
            : "Too many failed attempts. Please try again in 15 minutes.";
        respond(['success' => false, 'error' => $err], 401);
    }

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $tenant = resolveTenant();

    $session = [
        'id'             => $user['id'],
        'employee_id'    => $user['employee_id'],
        'name'           => $user['name'],
        'email'          => $user['email'],
        'phone'          => $user['phone'],
        'department'     => $user['department'],
        'business_unit'  => $user['business_unit'],
        'location'       => $user['location'],
        'role'           => $user['role'],
        'manager_id'     => $user['manager_id'],
        'manager_name'   => $user['manager_name'],
        'points'         => $user['points'],
        'avatar_initials' => $user['avatar_initials'] ?? strtoupper(substr($user['name'], 0, 1)),
        'org_name'       => $tenant['name'],
        'org_slug'       => $tenant['slug'],
    ];

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user']          = $session;
    $_SESSION['org_slug']     = $tenant['slug'];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();

    clearFailedAttempts($loginId);
    respond(['success' => true, 'user' => $session]);
}

if ($action === 'logout') {
    session_destroy();
    respond(['success' => true]);
}

// ── FORGOT PASSWORD ───────────────────────────────────────────────
if ($action === 'forgot_password' && $method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = strtolower(trim($body['email'] ?? ''));
    $slug  = strtolower(preg_replace('/[^a-z0-9_-]/', '', trim($body['org_slug'] ?? '')));

    if (!$email) respond(['success' => false, 'error' => 'Email is required.'], 400);

    if ($slug) $_GET['org'] = $slug;

    $stmt = db()->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Always return success to prevent email enumeration
    if (!$user) {
        respond(['success' => true, 'message' => 'If an account with that email exists, a reset link has been sent.']);
    }

    // Invalidate any existing tokens for this user
    $pdo = db();
    $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?")->execute([$user['id']]);

    // Generate a secure token (64-char hex)
    $token = bin2hex(random_bytes(32));
    $hashedToken = password_hash($token, PASSWORD_BCRYPT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $pdo->prepare(
        "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)"
    )->execute([$user['id'], $hashedToken, $expiresAt]);

    // Get SMTP settings
    try {
        $settingsStmt = $pdo->prepare("SELECT value FROM org_settings WHERE key_name = 'email_enabled' LIMIT 1");
        $settingsStmt->execute();
        $emailEnabled = $settingsStmt->fetchColumn() === '1';

        if ($emailEnabled) {
            $slaStmt = $pdo->prepare("SELECT value FROM org_settings WHERE key_name = 'smtp_host' LIMIT 1");
            $slaStmt->execute();
            $smtpHost = $slaStmt->fetchColumn();

            if ($smtpHost) {
                $settings = [];
                foreach (['smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','smtp_from_name'] as $k) {
                    $s = $pdo->prepare("SELECT value FROM org_settings WHERE key_name = ? LIMIT 1");
                    $s->execute([$k]);
                    $settings[$k] = $s->fetchColumn() ?? '';
                }

                $resetUrl = getAppBaseUrl() . 'index.php?reset_token=' . urlencode($token) . ($slug ? '&org=' . $slug : '');
                $subject = 'Reset Your IFQM Password';
                $htmlBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;padding:20px;color:#1e293b">'
                    . '<h2 style="color:#4f46e5">IFQM – Password Reset Request</h2>'
                    . '<p>Hi ' . esc($user['name']) . ',</p>'
                    . '<p>We received a request to reset your IFQM account password. Click the button below to set a new password. This link expires in 1 hour.</p>'
                    . '<p><a href="' . esc($resetUrl) . '" style="display:inline-block;background:#4f46e5;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold;">Reset Password</a></p>'
                    . '<p style="color:#64748b;font-size:12px">If you did not request this, you can safely ignore this email. The link will expire automatically.</p>'
                    . '</body></html>';

                require_once __DIR__ . '/mailer.php';
                sendSmtpEmail($settings, $email, $user['name'], $subject, $htmlBody);
            }
        }
    } catch (Exception $e) {
        error_log('Password reset email error: ' . $e->getMessage());
    }

    respond(['success' => true, 'message' => 'If an account with that email exists, a reset link has been sent.']);
}

// ── RESET PASSWORD ────────────────────────────────────────────────
if ($action === 'reset_password' && $method === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $token   = trim($body['token'] ?? '');
    $password = trim($body['password'] ?? '');
    $orgSlug = strtolower(preg_replace('/[^a-z0-9_-]/', '', trim($body['org_slug'] ?? '')));

    if (!$token || !$password) {
        respond(['success' => false, 'error' => 'Token and new password are required.'], 400);
    }
    if (strlen($password) < 8) {
        respond(['success' => false, 'error' => 'Password must be at least 8 characters.'], 400);
    }

    if ($orgSlug) $_GET['org'] = $orgSlug;
    $pdo = db();

    // Find all tokens and check with password_verify
    $tokens = $pdo->query("SELECT id, user_id, token_hash, expires_at FROM password_reset_tokens WHERE expires_at > NOW()")->fetchAll();
    $matchedId = null;
    foreach ($tokens as $t) {
        if (password_verify($token, $t['token_hash'])) {
            $matchedId = $t['id'];
            $userId = $t['user_id'];
            break;
        }
    }

    if ($matchedId === null) {
        respond(['success' => false, 'error' => 'Invalid or expired reset link. Please request a new one.'], 400);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $userId]);
    $pdo->prepare("DELETE FROM password_reset_tokens WHERE id = ?")->execute([$matchedId]);

    respond(['success' => true, 'message' => 'Password updated successfully. Please log in with your new password.']);
}

// ── CHECK RESET TOKEN VALIDITY ───────────────────────────────────
if ($action === 'check_reset_token' && $method === 'GET') {
    $token = trim($_GET['token'] ?? '');
    if (!$token) respond(['success' => false, 'error' => 'Token required.'], 400);
    if ($orgSlug = strtolower(preg_replace('/[^a-z0-9_-]/', '', trim($_GET['org'] ?? '')))) {
        $_GET['org'] = $orgSlug;
    }

    $pdo = db();
    $tokens = $pdo->query("SELECT id, expires_at FROM password_reset_tokens WHERE expires_at > NOW()")->fetchAll();
    $valid = false;
    foreach ($tokens as $t) {
        $allTokens = $pdo->prepare("SELECT token_hash FROM password_reset_tokens WHERE id = ?");
        $allTokens->execute([$t['id']]);
        $hash = $allTokens->fetchColumn();
        if (password_verify($token, $hash)) { $valid = true; break; }
    }

    respond(['success' => true, 'valid' => $valid]);
}

respond(['success' => false, 'error' => 'Unknown action'], 400);
