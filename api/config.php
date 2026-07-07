<?php
// ── Master DB connection (tenant registry) ─────────────────────────
define('MASTER_DB_HOST', 'localhost');
define('MASTER_DB_USER', 'root');
define('MASTER_DB_PASS', '');
define('MASTER_DB_NAME', 'ifqm_master');

// ── Fallback for fresh installs without master DB ──────────────────
define('FALLBACK_DB_HOST', 'localhost');
define('FALLBACK_DB_USER', 'root');
define('FALLBACK_DB_PASS', '');
define('FALLBACK_DB_NAME', 'ifqm_ideation');

define('GEMINI_API_KEY', '');

define('MAX_FILE_MB',       10);
define('SESSION_LIFETIME',  28800);
define('POINTS_SUBMIT',     10);
define('POINTS_APPROVED',   25);
define('POINTS_IMPLEMENTED',65);

// ── App base URL ───────────────────────────────────────────────────
function getAppBaseUrl(): string {
    $proto  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/ifqm/api/config.php'));
    return $proto . '://' . $host . rtrim($script, '/\\') . '/';
}

// ── Master DB connection ───────────────────────────────────────────
function masterDb(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO(
        'mysql:host=' . MASTER_DB_HOST . ';dbname=' . MASTER_DB_NAME . ';charset=utf8mb4',
        MASTER_DB_USER, MASTER_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    return $pdo;
}

// ── Tenant resolution ─────────────────────────────────────────────
// Priority: 1) session org_slug  2) ?org= URL param  3) domain  4) default  5) fallback
function resolveTenant(): array {
    static $tenant = null;
    if ($tenant !== null) return $tenant;

    if (session_status() === PHP_SESSION_NONE) @session_start();

    // 1. Session-stored slug (persisted after login)
    $slug = $_SESSION['org_slug'] ?? null;

    // 2. URL ?org= parameter (initial page load or login request)
    if (!$slug && isset($_GET['org'])) {
        $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', trim($_GET['org'])));
        if (!$slug) $slug = null;
    }

    $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));

    try {
        $master = masterDb();

        if ($slug) {
            $stmt = $master->prepare("SELECT * FROM tenants WHERE slug=? AND status='active' LIMIT 1");
            $stmt->execute([$slug]);
            $row = $stmt->fetch();
            if ($row) { $tenant = $row; return $tenant; }
        }

        // 3. Domain-based
        $stmt = $master->prepare("SELECT * FROM tenants WHERE domain=? AND status='active' LIMIT 1");
        $stmt->execute([$host]);
        $row = $stmt->fetch();

        if (!$row) {
            // 4. Default tenant
            $stmt = $master->prepare("SELECT * FROM tenants WHERE is_default=1 AND status='active' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
        }

        if ($row) { $tenant = $row; return $tenant; }
    } catch (Exception $e) {
        error_log('ifqm_master unavailable, using fallback tenant: ' . $e->getMessage());
    }

    // 5. Built-in fallback — keeps existing single-tenant installs working
    $tenant = [
        'id'            => 0,
        'name'          => 'IFQM',
        'slug'          => 'ifqm',
        'domain'        => $host,
        'db_host'       => FALLBACK_DB_HOST,
        'db_name'       => FALLBACK_DB_NAME,
        'db_user'       => FALLBACK_DB_USER,
        'db_pass'       => FALLBACK_DB_PASS,
        'status'        => 'active',
        'is_default'    => 1,
        'primary_color' => '#4f46e5',
    ];
    return $tenant;
}

// ── Per-tenant DB connection ───────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $t = resolveTenant();
    try {
        $pdo = new PDO(
            'mysql:host=' . $t['db_host'] . ';dbname=' . $t['db_name'] . ';charset=utf8mb4',
            $t['db_user'], $t['db_pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('DB connection failed for tenant ' . $t['slug'] . ': ' . $e->getMessage());
        die(json_encode(['success' => false, 'error' => 'Database connection failed.']));
    }
    return $pdo;
}

// ── Per-tenant upload paths ────────────────────────────────────────
function uploadDir(): string {
    static $dir = null;
    if ($dir) return $dir;
    $slug = resolveTenant()['slug'] ?? 'ifqm';
    $dir  = __DIR__ . '/uploads/' . $slug . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function uploadUrl(): string {
    $slug = resolveTenant()['slug'] ?? 'ifqm';
    return getAppBaseUrl() . 'api/uploads/' . $slug . '/';
}

// Keep constant for backward compat (base dir, non-tenant-specific)
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// ── Core helpers ──────────────────────────────────────────────────
function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function respond(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── CSRF token helpers ─────────────────────────────────────────────
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token']) || (time() - ($_SESSION['csrf_token_time'] ?? 0)) > 3600) {
        $_SESSION['csrf_token']    = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? ($_SERVER['HTTP_X_XSRF_TOKEN'] ?? '');
    if (!validateCsrfToken($token)) {
        respond(['success' => false, 'error' => 'Invalid or missing security token. Please refresh the page and try again.'], 403);
    }
}

function requireAuth(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) respond(['success' => false, 'error' => 'Not authenticated'], 401);
    // Idle-session timeout
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_destroy();
        respond(['success' => false, 'error' => 'Session expired', 'expired' => true], 401);
    }
    $_SESSION['last_activity'] = time();
    return $_SESSION['user'];
}

function requireRole(string ...$roles): array {
    $user = requireAuth();
    if (!in_array($user['role'], $roles, true)) respond(['success' => false, 'error' => 'Insufficient permissions'], 403);
    return $user;
}

// Platform-level auth — only for IFQM vendor staff (ifqm_master.platform_admins)
function requirePlatformAuth(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['platform_admin']) || empty($_SESSION['user'])) {
        respond(['success' => false, 'error' => 'Not authenticated as platform admin'], 401);
    }
    return $_SESSION['user'];
}

function generateIdeaCode(): string {
    $year = (int)date('Y');
    $stmt = db()->prepare("SELECT COUNT(*) FROM ideas WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $n = (int)$stmt->fetchColumn() + 1;
    return 'IDA-' . $year . '-' . str_pad($n, 3, '0', STR_PAD_LEFT);
}

function addNotification(int $userId, string $title, string $msg, ?int $ideaId = null): void {
    db()->prepare("INSERT INTO notifications (user_id,title,message,idea_id) VALUES (?,?,?,?)")
        ->execute([$userId, $title, $msg, $ideaId]);
}

function addWorkflow(int $ideaId, int $actorId, string $action, ?string $comment = null): void {
    db()->prepare("INSERT INTO idea_workflow (idea_id,actor_id,action,comment) VALUES (?,?,?,?)")
        ->execute([$ideaId, $actorId, $action, $comment]);
}

function addPoints(int $userId, int $pts): void {
    db()->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$pts, $userId]);
}

function callGemini(string $prompt): ?string {
    $apiKey = GEMINI_API_KEY;
    if (!$apiKey) return null;
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . urlencode($apiKey);
    $payload = [
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 150],
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $raw = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($err) { error_log('Gemini cURL error: ' . $err); return null; }
    if ($httpCode !== 200) { error_log('Gemini HTTP ' . $httpCode . ': ' . $raw); return null; }
    $decoded = json_decode($raw, true);
    $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($content !== null) error_log('Gemini raw content: ' . $content);
    return $content;
}
