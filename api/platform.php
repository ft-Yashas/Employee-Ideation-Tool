<?php
require_once __DIR__ . '/config.php';

$user   = requirePlatformAuth();
$action = $_GET['action'] ?? 'tenants';
$master = masterDb();

// ── Helper: open a read-only connection to a tenant DB ────────────────────
function tenantPdo(array $t): PDO {
    return new PDO(
        'mysql:host=' . $t['db_host'] . ';dbname=' . $t['db_name'] . ';charset=utf8mb4',
        $t['db_user'], $t['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

// ── Helper: strip sensitive DB credentials before sending to client ────────
function safeTenant(array $t): array {
    unset($t['db_host'], $t['db_name'], $t['db_user'], $t['db_pass']);
    return $t;
}

// ── GET ?action=tenants ───────────────────────────────────────────────────
// Returns all tenants with aggregate stats only — no idea content.
if ($action === 'tenants') {
    $tenants = $master->query("SELECT * FROM tenants ORDER BY created_at ASC")->fetchAll();

    foreach ($tenants as &$t) {
        try {
            $pdo = tenantPdo($t);

            $t['user_count']        = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role != 'super_admin'")->fetchColumn();
            $t['idea_count']        = (int)$pdo->query("SELECT COUNT(*) FROM ideas WHERE status != 'Draft'")->fetchColumn();
            $t['implemented_count'] = (int)$pdo->query("SELECT COUNT(*) FROM ideas WHERE status = 'Implemented'")->fetchColumn();
            $t['last_activity']     = $pdo->query("SELECT MAX(submitted_at) FROM ideas WHERE status != 'Draft'")->fetchColumn() ?: null;

            // Submission trend — last 6 months (counts only, no content)
            $t['trend'] = $pdo->query(
                "SELECT DATE_FORMAT(submitted_at,'%Y-%m') AS month, COUNT(*) AS cnt
                 FROM ideas WHERE submitted_at IS NOT NULL AND status != 'Draft'
                 GROUP BY month ORDER BY month DESC LIMIT 6"
            )->fetchAll();

        } catch (Exception $e) {
            $t['user_count']        = 0;
            $t['idea_count']        = 0;
            $t['implemented_count'] = 0;
            $t['last_activity']     = null;
            $t['trend']             = [];
            $t['db_error']          = true;
        }

        $t = safeTenant($t);
    }
    unset($t);

    respond(['success' => true, 'tenants' => $tenants]);
}

// ── GET ?action=tenant_hierarchy&id=X ────────────────────────────────────
// Returns the user tree for one tenant.
// Privacy contract: names, roles, departments, manager name, idea COUNT.
// No idea titles, descriptions, content, or scores are returned.
if ($action === 'tenant_hierarchy') {
    $tenantId = (int)($_GET['id'] ?? 0);
    if (!$tenantId) respond(['success' => false, 'error' => 'Missing tenant id.'], 400);

    $stmt = $master->prepare("SELECT * FROM tenants WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$tenantId]);
    $t = $stmt->fetch();
    if (!$t) respond(['success' => false, 'error' => 'Tenant not found.'], 404);

    try {
        $pdo   = tenantPdo($t);
        $users = $pdo->query(
            "SELECT u.id, u.employee_id, u.name, u.department, u.business_unit,
                    u.location, u.role, u.manager_id,
                    m.name AS manager_name,
                    (SELECT COUNT(*) FROM ideas
                     WHERE submitter_id = u.id AND status != 'Draft') AS idea_count
             FROM users u
             LEFT JOIN users m ON m.id = u.manager_id
             WHERE u.role != 'super_admin'
             ORDER BY FIELD(u.role,'admin','executive','senior_manager','manager','project_lead','team_lead','employee','trainee'), u.name"
        )->fetchAll();

        respond([
            'success' => true,
            'tenant'  => ['id' => $t['id'], 'name' => $t['name'], 'slug' => $t['slug'], 'domain' => $t['domain']],
            'users'   => $users,
        ]);
    } catch (Exception $e) {
        respond(['success' => false, 'error' => 'Tenant database is unavailable.'], 503);
    }
}

// ── POST ?action=create_tenant ────────────────────────────────────────────
// Creates a new MSME organisation: database, schema, first admin user, tenant record.
if ($action === 'create_tenant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body       = json_decode(file_get_contents('php://input'), true) ?? [];
    $orgName    = trim($body['org_name'] ?? '');
    $slug       = strtolower(preg_replace('/[^a-z0-9_-]/', '', trim($body['slug'] ?? '')));
    $adminName  = trim($body['admin_name'] ?? '');
    $adminEmail = strtolower(trim($body['admin_email'] ?? ''));
    $adminPass  = $body['admin_password'] ?? '';
    $color      = preg_match('/^#[0-9a-fA-F]{6}$/', $body['primary_color'] ?? '') ? $body['primary_color'] : '#4f46e5';

    if (!$orgName || !$slug || !$adminName || !$adminEmail || !$adminPass) {
        respond(['success' => false, 'error' => 'All fields are required.'], 400);
    }
    if (strlen($slug) < 2 || strlen($slug) > 30) {
        respond(['success' => false, 'error' => 'Org code must be 2–30 characters.'], 400);
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        respond(['success' => false, 'error' => 'Invalid admin email address.'], 400);
    }
    if (strlen($adminPass) < 6) {
        respond(['success' => false, 'error' => 'Admin password must be at least 6 characters.'], 400);
    }

    // Uniqueness checks
    $chk = $master->prepare("SELECT id FROM tenants WHERE slug=? LIMIT 1");
    $chk->execute([$slug]);
    if ($chk->fetch()) respond(['success' => false, 'error' => 'Organization code already in use.'], 409);

    $dbName    = 'ifqm_' . preg_replace('/[^a-z0-9_]/', '_', $slug);
    $adminEmpId = strtoupper($slug) . '-ADMIN';

    try {
        // 1. Create the database
        $master->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // 2. Connect and run schema
        $tPdo = new PDO(
            "mysql:host=" . MASTER_DB_HOST . ";dbname={$dbName};charset=utf8mb4",
            MASTER_DB_USER, MASTER_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $schemaFile = __DIR__ . '/../schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception('schema.sql not found at ' . $schemaFile);
        }
        $schema = file_get_contents($schemaFile);
        // Split on semicolons but skip empty statements
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $sql) {
            $tPdo->exec($sql);
        }

        // 3. Create first admin user (admin role — full org control)
        $initials = implode('', array_map(
            fn($w) => strtoupper($w[0]),
            array_slice(array_filter(explode(' ', $adminName)), 0, 2)
        ));
        $hash = password_hash($adminPass, PASSWORD_BCRYPT);
        $tPdo->prepare(
            "INSERT INTO users (employee_id, name, email, password_hash, role, avatar_initials, status)
             VALUES (?, ?, ?, ?, 'admin', ?, 'active')"
        )->execute([$adminEmpId, $adminName, $adminEmail, $hash, $initials ?: 'OA']);

        // 4a. Insert default approval workflow settings
        $approvalDefaults = [
            ['approval_mode',                'default'],
            ['approval_reviewer_roles',       'team_lead,project_lead,manager,senior_manager'],
            ['approval_final_approver_roles', 'executive,admin,super_admin'],
            ['approval_threshold',            '100'],
        ];
        $insDef = $tPdo->prepare(
            "INSERT INTO org_settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=value"
        );
        foreach ($approvalDefaults as $row) $insDef->execute($row);

        // 4b. Register tenant in master DB
        $master->prepare(
            "INSERT INTO tenants (name, slug, domain, db_host, db_name, db_user, db_pass, status, is_default, primary_color)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 0, ?)"
        )->execute([$orgName, $slug, $slug . '.localhost', MASTER_DB_HOST, $dbName, MASTER_DB_USER, MASTER_DB_PASS, $color]);

        $tenantId = (int)$master->lastInsertId();

        respond([
            'success'    => true,
            'tenant_id'  => $tenantId,
            'org_name'   => $orgName,
            'slug'       => $slug,
            'db_name'    => $dbName,
            'login_url'  => '?org=' . $slug,
            'admin_email'=> $adminEmail,
        ]);

    } catch (Exception $e) {
        // Roll back: drop DB if it was created
        try { $master->exec("DROP DATABASE IF EXISTS `{$dbName}`"); } catch (Exception $ex) {}
        respond(['success' => false, 'error' => 'Failed to create organisation: ' . $e->getMessage()], 500);
    }
}

// ── GET ?action=tenant_detail&id=X ───────────────────────────────────────
// Detailed view of a single tenant for platform admin (read-only).
if ($action === 'tenant_detail') {
    $tenantId = (int)($_GET['id'] ?? 0);
    if (!$tenantId) respond(['success' => false, 'error' => 'Missing tenant id.'], 400);

    $stmt = $master->prepare("SELECT * FROM tenants WHERE id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    $t = $stmt->fetch();
    if (!$t) respond(['success' => false, 'error' => 'Tenant not found.'], 404);

    try {
        $pdo = tenantPdo($t);

        $users = $pdo->query(
            "SELECT u.id, u.employee_id, u.name, u.email, u.department, u.business_unit,
                    u.location, u.role, u.status, u.points, u.manager_id, u.created_at,
                    m.name AS manager_name,
                    (SELECT COUNT(*) FROM ideas WHERE submitter_id=u.id AND status!='Draft') AS idea_count
             FROM users u LEFT JOIN users m ON m.id=u.manager_id
             ORDER BY FIELD(u.role,'admin','executive','senior_manager','manager','project_lead','team_lead','employee','trainee'), u.name"
        )->fetchAll();

        $ideaStats = $pdo->query(
            "SELECT status, COUNT(*) AS cnt FROM ideas WHERE status!='Draft' GROUP BY status"
        )->fetchAll();

        respond([
            'success'    => true,
            'tenant'     => safeTenant($t),
            'users'      => $users,
            'idea_stats' => $ideaStats,
        ]);
    } catch (Exception $e) {
        respond(['success' => false, 'error' => 'Tenant database is unavailable.'], 503);
    }
}

respond(['success' => false, 'error' => 'Unknown action.'], 400);
