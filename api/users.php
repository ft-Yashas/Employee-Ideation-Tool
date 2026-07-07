<?php
require_once __DIR__ . '/config.php';

$user   = requireAuth();
$action = $_GET['action'] ?? 'list';
$pdo    = db();

if ($action === 'list') {
    $q    = '%' . trim($_GET['q'] ?? '') . '%';
    $stmt = $pdo->prepare(
        "SELECT id, employee_id, name, department, email, role, avatar_initials
         FROM users WHERE (name LIKE ? OR employee_id LIKE ? OR email LIKE ?)
         AND id != ? LIMIT 20"
    );
    $stmt->execute([$q, $q, $q, $user['id']]);
    respond(['success' => true, 'users' => $stmt->fetchAll()]);
}

if ($action === 'admin_users') {
    requireRole('admin', 'super_admin');
    $stmt = $pdo->query(
        "SELECT u.id, u.employee_id, u.name, u.department, u.business_unit, u.location,
                u.email, u.role, u.avatar_initials, u.points, u.status, u.manager_id,
                m.name AS manager_name
         FROM users u LEFT JOIN users m ON m.id=u.manager_id
         ORDER BY FIELD(u.role,'admin','executive','senior_manager','manager','project_lead','team_lead','employee','trainee'), u.name"
    );
    respond(['success' => true, 'users' => $stmt->fetchAll()]);
}

// ── Create user (admin / super_admin) ─────────────────────────────────────
if ($action === 'create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    requireRole('admin', 'super_admin');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $name         = trim($body['name'] ?? '');
    $email        = strtolower(trim($body['email'] ?? ''));
    $password     = $body['password'] ?? '';
    $employeeId   = trim($body['employee_id'] ?? '');
    $department   = trim($body['department'] ?? '');
    $businessUnit = trim($body['business_unit'] ?? '');
    $location     = trim($body['location'] ?? '');
    $role         = $body['role'] ?? 'employee';
    $managerId    = !empty($body['manager_id']) ? (int)$body['manager_id'] : null;

    if (!$name || !$email || !$password || !$employeeId) {
        respond(['success' => false, 'error' => 'Name, email, employee ID, and password are required.'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(['success' => false, 'error' => 'Invalid email address.'], 400);
    }
    if (strlen($password) < 6) {
        respond(['success' => false, 'error' => 'Password must be at least 6 characters.'], 400);
    }

    // admin can create any role except admin/super_admin; super_admin can also create admin
    $allowed = $user['role'] === 'super_admin'
        ? ['trainee','employee','team_lead','project_lead','manager','senior_manager','executive','admin']
        : ['trainee','employee','team_lead','project_lead','manager','senior_manager','executive'];
    if (!in_array($role, $allowed, true)) {
        respond(['success' => false, 'error' => 'You cannot assign that role.'], 403);
    }

    // Uniqueness check
    $chk = $pdo->prepare("SELECT id FROM users WHERE email=? OR employee_id=? LIMIT 1");
    $chk->execute([$email, $employeeId]);
    if ($chk->fetch()) {
        respond(['success' => false, 'error' => 'Email or employee ID already exists.'], 409);
    }

    $initials = implode('', array_map(
        fn($w) => strtoupper($w[0]),
        array_slice(array_filter(explode(' ', $name)), 0, 2)
    ));
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $pdo->prepare(
        "INSERT INTO users (employee_id, name, email, password_hash, department, business_unit,
                            location, role, manager_id, avatar_initials, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,'active')"
    )->execute([$employeeId, $name, $email, $hash, $department, $businessUnit, $location, $role, $managerId, $initials ?: strtoupper($name[0])]);

    respond(['success' => true, 'user_id' => (int)$pdo->lastInsertId()]);
}

// ── Update user ────────────────────────────────────────────────────────────
if ($action === 'update_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    requireRole('admin', 'super_admin');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($body['id'] ?? 0);
    if (!$id) respond(['success' => false, 'error' => 'Missing user ID.'], 400);

    // Fetch target
    $tgt = $pdo->prepare("SELECT id, role FROM users WHERE id=? LIMIT 1");
    $tgt->execute([$id]);
    $target = $tgt->fetch();
    if (!$target) respond(['success' => false, 'error' => 'User not found.'], 404);
    if ($target['role'] === 'super_admin') respond(['success' => false, 'error' => 'Cannot edit super admin.'], 403);
    if ($id === (int)$user['id']) respond(['success' => false, 'error' => 'Cannot edit your own account here.'], 403);

    $name         = trim($body['name'] ?? '');
    $department   = trim($body['department'] ?? '');
    $businessUnit = trim($body['business_unit'] ?? '');
    $location     = trim($body['location'] ?? '');
    $role         = $body['role'] ?? $target['role'];
    $managerId    = !empty($body['manager_id']) ? (int)$body['manager_id'] : null;
    $status       = ($body['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    $allowed = $user['role'] === 'super_admin'
        ? ['trainee','employee','team_lead','project_lead','manager','senior_manager','executive','admin']
        : ['trainee','employee','team_lead','project_lead','manager','senior_manager','executive'];
    if (!in_array($role, $allowed, true)) {
        respond(['success' => false, 'error' => 'You cannot assign that role.'], 403);
    }

    $initials = implode('', array_map(
        fn($w) => strtoupper($w[0]),
        array_slice(array_filter(explode(' ', $name)), 0, 2)
    ));

    $pdo->prepare(
        "UPDATE users SET name=?, department=?, business_unit=?, location=?, role=?,
                          manager_id=?, avatar_initials=?, status=? WHERE id=?"
    )->execute([$name, $department, $businessUnit, $location, $role, $managerId, $initials ?: strtoupper($name[0]), $status, $id]);

    respond(['success' => true]);
}

// ── Delete user ────────────────────────────────────────────────────────────
if ($action === 'delete_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    requireRole('admin', 'super_admin');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($body['id'] ?? 0);
    if (!$id) respond(['success' => false, 'error' => 'Missing user ID.'], 400);
    if ($id === (int)$user['id']) respond(['success' => false, 'error' => 'Cannot delete your own account.'], 403);

    $tgt = $pdo->prepare("SELECT role FROM users WHERE id=? LIMIT 1");
    $tgt->execute([$id]);
    $target = $tgt->fetch();
    if (!$target) respond(['success' => false, 'error' => 'User not found.'], 404);
    if ($target['role'] === 'super_admin') respond(['success' => false, 'error' => 'Cannot delete super admin.'], 403);

    // If user has submitted ideas, deactivate instead of deleting
    $ic = $pdo->prepare("SELECT COUNT(*) FROM ideas WHERE submitter_id=? AND status!='Draft'");
    $ic->execute([$id]);
    if ((int)$ic->fetchColumn() > 0) {
        $pdo->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$id]);
        respond(['success' => true, 'deactivated' => true, 'message' => 'User has submitted ideas — account deactivated instead of deleted.']);
    }

    $pdo->prepare("UPDATE users SET manager_id=NULL WHERE manager_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    respond(['success' => true, 'deleted' => true]);
}

// ── Managers list for dropdowns ────────────────────────────────────────────
if ($action === 'managers') {
    requireRole('admin', 'super_admin');
    $stmt = $pdo->query(
        "SELECT id, name, department, role FROM users
         WHERE role IN ('team_lead','project_lead','manager','senior_manager','executive','admin') AND status='active'
         ORDER BY FIELD(role,'admin','executive','senior_manager','manager','project_lead','team_lead'), name"
    );
    respond(['success' => true, 'managers' => $stmt->fetchAll()]);
}

if ($action === 'hierarchy') {
    requireRole('super_admin');
    $stmt = $pdo->query(
        "SELECT u.id, u.employee_id, u.name, u.email, u.department, u.business_unit,
                u.location, u.role, u.manager_id, u.points, u.avatar_initials,
                m.name AS manager_name,
                (SELECT COUNT(*) FROM ideas WHERE submitter_id = u.id AND status != 'Draft') AS idea_count
         FROM users u
         LEFT JOIN users m ON m.id = u.manager_id
         WHERE u.role != 'super_admin'
         ORDER BY FIELD(u.role,'admin','executive','senior_manager','manager','project_lead','team_lead','employee','trainee'), u.name"
    );
    $users = $stmt->fetchAll();

    $stats = [
        'total'      => 0,
        'admins'     => 0,
        'managers'   => 0,
        'employees'  => 0,
        'executives' => 0,
    ];
    foreach ($users as $u) {
        $stats['total']++;
        $key = $u['role'] . 's';
        if (isset($stats[$key])) $stats[$key]++;
    }

    respond(['success' => true, 'users' => $users, 'stats' => $stats]);
}

if ($action === 'leaderboard') {
    $period = $_GET['period'] ?? 'all';

    $dateFilter = '';
    switch ($period) {
        case 'monthly':
            $dateFilter = "AND MONTH(i.submitted_at)=MONTH(NOW()) AND YEAR(i.submitted_at)=YEAR(NOW())";
            break;
        case 'quarterly':
            $dateFilter = "AND QUARTER(i.submitted_at)=QUARTER(NOW()) AND YEAR(i.submitted_at)=YEAR(NOW())";
            break;
        case 'yearly':
            $dateFilter = "AND YEAR(i.submitted_at)=YEAR(NOW())";
            break;
    }

    $stmt = $pdo->query(
        "SELECT u.id, u.name, u.department, u.business_unit, u.points, u.avatar_initials,
                COUNT(DISTINCT i.id) AS idea_count,
                SUM(CASE WHEN i.status='Implemented' THEN 1 ELSE 0 END) AS implemented_count,
                ROUND(AVG(CASE WHEN i.status != 'Draft' THEN i.ai_score ELSE NULL END), 1) AS avg_score,
                (SELECT COUNT(*) FROM idea_votes iv
                 JOIN ideas i2 ON i2.id = iv.idea_id WHERE i2.submitter_id = u.id) AS total_votes_received,
                (SELECT ROUND(AVG(iv.rating),1) FROM idea_votes iv
                 JOIN ideas i2 ON i2.id = iv.idea_id WHERE i2.submitter_id = u.id) AS avg_community_rating
         FROM users u
         LEFT JOIN ideas i ON i.submitter_id = u.id $dateFilter
         WHERE u.role NOT IN ('admin','super_admin')
         GROUP BY u.id
         ORDER BY u.points DESC
         LIMIT 20"
    );
    $individuals = $stmt->fetchAll();

    $dstmt = $pdo->query(
        "SELECT u.department,
                SUM(u.points)          AS dept_points,
                COUNT(DISTINCT u.id)   AS member_count,
                COUNT(DISTINCT i.id)   AS idea_count,
                ROUND(AVG(CASE WHEN i.status != 'Draft' THEN i.ai_score ELSE NULL END), 1) AS avg_score
         FROM users u
         LEFT JOIN ideas i ON i.submitter_id = u.id $dateFilter
         WHERE u.role NOT IN ('admin','super_admin')
         GROUP BY u.department
         ORDER BY dept_points DESC"
    );
    $departments = $dstmt->fetchAll();

    $topIdeasStmt = $pdo->query(
        "SELECT i.id, i.idea_code, i.title, i.ai_score, i.status,
                i.impact_level, i.impact_areas,
                u.name AS submitter_name, u.department
         FROM ideas i
         JOIN users u ON u.id = i.submitter_id
         WHERE i.status != 'Draft' AND i.ai_score > 0
         ORDER BY i.ai_score DESC
         LIMIT 5"
    );
    $topIdeas = $topIdeasStmt->fetchAll();

    respond([
        'success'     => true,
        'individuals' => $individuals,
        'departments' => $departments,
        'top_ideas'   => $topIdeas,
    ]);
}

if ($action === 'notifications') {
    $stmt = $pdo->prepare(
        "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20"
    );
    $stmt->execute([$user['id']]);
    $notifs = $stmt->fetchAll();

    foreach ($notifs as &$n) {
        if (empty($n['idea_id']) && preg_match('/IDA-\d{4}-\d{3}/', $n['message'] ?? '', $m)) {
            $row = $pdo->prepare("SELECT id FROM ideas WHERE idea_code = ? LIMIT 1");
            $row->execute([$m[0]]);
            $found = $row->fetchColumn();
            if ($found) {
                $n['idea_id'] = (int)$found;
                $pdo->prepare("UPDATE notifications SET idea_id=? WHERE id=?")->execute([$found, $n['id']]);
            }
        }
    }
    unset($n);

    $unread = array_filter($notifs, fn($n) => !$n['is_read']);
    respond(['success' => true, 'notifications' => $notifs, 'unread_count' => count($unread)]);
}

if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
    respond(['success' => true]);
}

if ($action === 'analytics') {
    requireRole('admin', 'executive', 'manager', 'senior_manager', 'super_admin');

    $trend = $pdo->query(
        "SELECT DATE_FORMAT(submitted_at,'%Y-%m') AS month,
                COUNT(*) AS total,
                SUM(CASE WHEN status='Implemented' THEN 1 ELSE 0 END) AS implemented,
                ROUND(AVG(ai_score), 1) AS avg_score
         FROM ideas WHERE submitted_at IS NOT NULL
         GROUP BY month ORDER BY month DESC LIMIT 12"
    )->fetchAll();

    $impactRaw = $pdo->query(
        "SELECT impact_areas FROM ideas WHERE impact_areas IS NOT NULL"
    )->fetchAll();
    $impactCount = [];
    foreach ($impactRaw as $row) {
        foreach (explode(',', $row['impact_areas']) as $area) {
            $area = trim($area);
            if ($area) $impactCount[$area] = ($impactCount[$area] ?? 0) + 1;
        }
    }
    arsort($impactCount);

    $statusSummary = $pdo->query(
        "SELECT status, COUNT(*) AS cnt FROM ideas GROUP BY status"
    )->fetchAll();

    $scoreStmt = $pdo->query(
        "SELECT
            SUM(CASE WHEN ai_score >= 75 THEN 1 ELSE 0 END) AS high_quality,
            SUM(CASE WHEN ai_score >= 50 AND ai_score < 75 THEN 1 ELSE 0 END) AS medium_quality,
            SUM(CASE WHEN ai_score > 0 AND ai_score < 50 THEN 1 ELSE 0 END) AS low_quality,
            ROUND(AVG(CASE WHEN status != 'Draft' THEN ai_score ELSE NULL END), 1) AS overall_avg
         FROM ideas WHERE status != 'Draft'"
    );
    $scoreStats = $scoreStmt->fetch() ?: [];

    respond([
        'success'             => true,
        'trend'               => $trend,
        'impact_distribution' => $impactCount,
        'status_summary'      => $statusSummary,
        'score_stats'         => $scoreStats,
    ]);
}

if ($action === 'audit') {
    requireRole('admin', 'manager', 'senior_manager', 'executive', 'super_admin');
    $stmt = $pdo->query(
        "SELECT w.*, u.name AS actor_name, u.role AS actor_role,
                i.idea_code, i.title AS idea_title,
                s.name AS submitter_name, s.department
         FROM idea_workflow w
         JOIN users u ON u.id = w.actor_id
         JOIN ideas i ON i.id = w.idea_id
         JOIN users s ON s.id = i.submitter_id
         ORDER BY w.created_at DESC LIMIT 200"
    );
    respond(['success' => true, 'audit' => $stmt->fetchAll()]);
}

if ($action === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $b     = json_decode(file_get_contents('php://input'), true) ?? [];
    $phone = trim($b['phone'] ?? '');
    $pdo->prepare("UPDATE users SET phone=? WHERE id=?")->execute([$phone, $user['id']]);
    $_SESSION['user']['phone'] = $phone;
    respond(['success' => true]);
}

respond(['success' => false, 'error' => 'Unknown action'], 400);
