<?php
/**
 * IFQM Tenant Provisioning Script
 * Usage: php provision_tenant.php --name="Acme Corp" --slug="acme" --domain="acme.example.com" [--db-pass="secret"] [--db-user="root"] [--db-host="localhost"] [--admin-email="admin@acme.example.com"] [--admin-pass="changeme"]
 */

if (php_sapi_name() !== 'cli') { die("Run from CLI only.\n"); }

$opts = getopt('', ['name:', 'slug:', 'domain:', 'db-pass::', 'db-user::', 'db-host::', 'admin-email::', 'admin-pass::']);

$name       = $opts['name']        ?? null;
$slug       = $opts['slug']        ?? null;
$domain     = $opts['domain']      ?? null;
$dbPass     = $opts['db-pass']     ?? '';
$dbUser     = $opts['db-user']     ?? 'root';
$dbHost     = $opts['db-host']     ?? 'localhost';
$adminEmail = $opts['admin-email'] ?? ('admin@' . ($domain ?? 'tenant.local'));
$adminPass  = $opts['admin-pass']  ?? 'changeme123';

if (!$name || !$slug || !$domain) {
    echo "ERROR: --name, --slug and --domain are required.\n";
    echo "Usage: php provision_tenant.php --name=\"Acme\" --slug=\"acme\" --domain=\"acme.example.com\"\n";
    exit(1);
}

if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
    echo "ERROR: --slug must be lowercase alphanumeric + underscores only.\n";
    exit(1);
}

$dbName = 'ifqm_' . $slug;

echo "=== IFQM Tenant Provisioning ===\n";
echo "Name   : $name\n";
echo "Slug   : $slug\n";
echo "Domain : $domain\n";
echo "DB     : $dbName on $dbHost\n\n";

// 1. Connect to master DB
require_once __DIR__ . '/api/config.php';
try {
    $master = masterDb();
    echo "[1/5] Connected to ifqm_master ✓\n";
} catch (Exception $e) {
    echo "ERROR: Cannot connect to ifqm_master: " . $e->getMessage() . "\n";
    echo "       Run master.sql first.\n";
    exit(1);
}

// Check duplicate
$dup = $master->prepare("SELECT id FROM tenants WHERE slug=? OR domain=?");
$dup->execute([$slug, $domain]);
if ($dup->fetch()) {
    echo "ERROR: A tenant with slug '$slug' or domain '$domain' already exists.\n";
    exit(1);
}

// 2. Create tenant database
try {
    $rootPdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[2/5] Created database `$dbName` ✓\n";
} catch (Exception $e) {
    echo "ERROR: Cannot create database: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Run schema.sql
$schemaFile = __DIR__ . '/schema.sql';
if (!file_exists($schemaFile)) {
    echo "ERROR: schema.sql not found at $schemaFile\n";
    exit(1);
}
try {
    $tenantPdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $sql = file_get_contents($schemaFile);
    // Execute statement by statement
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt) $tenantPdo->exec($stmt);
    }
    echo "[3/5] Schema applied ✓\n";
} catch (Exception $e) {
    echo "ERROR: Schema failed: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Create super admin user
$passHash = password_hash($adminPass, PASSWORD_BCRYPT);
$initials = strtoupper(substr($name, 0, 1)) . 'A';
try {
    $tenantPdo->prepare(
        "INSERT INTO users (employee_id,name,email,password_hash,department,business_unit,location,role,avatar_initials)
         VALUES (?,?,?,?,?,?,?,?,?)"
    )->execute(['SA-001', $name . ' Admin', $adminEmail, $passHash, $name, 'All Units', 'HQ', 'super_admin', $initials]);
    echo "[4/5] Super admin created: $adminEmail / $adminPass ✓\n";
} catch (Exception $e) {
    echo "WARNING: Could not create super admin: " . $e->getMessage() . "\n";
}

// 4b. Insert default approval workflow settings
try {
    $approvalDefaults = [
        ['approval_mode',                'default'],
        ['approval_reviewer_roles',       'team_lead,project_lead,manager,senior_manager'],
        ['approval_final_approver_roles', 'executive,admin,super_admin'],
        ['approval_threshold',            '100'],
    ];
    $insDef = $tenantPdo->prepare(
        "INSERT INTO org_settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=value"
    );
    foreach ($approvalDefaults as $row) $insDef->execute($row);
    echo "[4b/5] Approval defaults inserted ✓\n";
} catch (Exception $e) {
    echo "WARNING: Could not insert approval defaults: " . $e->getMessage() . "\n";
}

// 5. Register in master DB
try {
    $master->prepare(
        "INSERT INTO tenants (name, slug, domain, db_host, db_name, db_user, db_pass, status, is_default)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 0)"
    )->execute([$name, $slug, $domain, $dbHost, $dbName, $dbUser, $dbPass]);
    echo "[5/5] Tenant registered in ifqm_master ✓\n";
} catch (Exception $e) {
    echo "ERROR: Could not register tenant: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Provisioning complete! ===\n";
echo "Tenant '$name' is live at: $domain\n";
echo "Upload folder: api/uploads/$slug/\n";
$uploadDir = __DIR__ . '/api/uploads/' . $slug . '/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
echo "Done.\n";
