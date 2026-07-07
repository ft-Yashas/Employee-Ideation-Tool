<?php
// api/challenges.php  –  Innovation challenges
// GET  ?action=list            — all challenges with idea_count
// GET  ?action=get&id=X        — challenge detail + linked ideas
// POST ?action=create          — create a challenge
// POST ?action=update          — update a challenge (creator or admin/exec)
// POST ?action=delete          — delete a challenge (admin/super_admin only)
require_once __DIR__ . '/config.php';

$user   = requireAuth();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$pdo    = db();

// ── LIST all challenges ───────────────────────────────────────────
if ($action === 'list' && $method === 'GET') {
    $stmt = $pdo->query(
        "SELECT ch.*, u.name AS creator_name,
                (SELECT COUNT(*) FROM ideas i
                 WHERE i.challenge_id = ch.id AND i.status != 'Draft') AS idea_count
         FROM challenges ch
         LEFT JOIN users u ON u.id = ch.created_by
         ORDER BY (ch.status = 'active') DESC, ch.deadline ASC"
    );
    respond(['success' => true, 'challenges' => $stmt->fetchAll()]);
}

// ── GET single challenge with linked ideas ────────────────────────
if ($action === 'get' && $method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respond(['success' => false, 'error' => 'id is required.'], 400);

    $stmt = $pdo->prepare(
        "SELECT ch.*, u.name AS creator_name
         FROM challenges ch
         LEFT JOIN users u ON u.id = ch.created_by
         WHERE ch.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $challenge = $stmt->fetch();
    if (!$challenge) respond(['success' => false, 'error' => 'Challenge not found.'], 404);

    $ideasStmt = $pdo->prepare(
        "SELECT i.id, i.idea_code, i.title, i.status, i.ai_score,
                i.impact_level, i.impact_areas, i.submitted_at,
                u.name AS submitter_name, u.department, u.avatar_initials
         FROM ideas i
         JOIN users u ON u.id = i.submitter_id
         WHERE i.challenge_id = ? AND i.status != 'Draft'
         ORDER BY i.ai_score DESC, i.submitted_at ASC"
    );
    $ideasStmt->execute([$id]);
    $challenge['ideas'] = $ideasStmt->fetchAll();

    respond(['success' => true, 'challenge' => $challenge]);
}

// ── CREATE a challenge ────────────────────────────────────────────
if ($action === 'create' && $method === 'POST') {
    requireCsrf();
    requireRole('admin', 'executive', 'manager', 'senior_manager', 'super_admin');
    $b           = json_decode(file_get_contents('php://input'), true) ?? [];
    $title       = trim($b['title'] ?? '');
    $description = trim($b['description'] ?? '');
    $deadline    = trim($b['deadline'] ?? '');

    if ($title === '') respond(['success' => false, 'error' => 'Title is required.'], 400);

    // Validate deadline format if provided
    if ($deadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}/', $deadline)) {
        respond(['success' => false, 'error' => 'Deadline must be a valid date (YYYY-MM-DD).'], 400);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO challenges (title, description, deadline, created_by, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, 'active', NOW(), NOW())"
    );
    $stmt->execute([$title, $description ?: null, $deadline ?: null, $user['id']]);
    $newId = (int)$pdo->lastInsertId();

    respond(['success' => true, 'id' => $newId]);
}

// ── UPDATE a challenge ────────────────────────────────────────────
if ($action === 'update' && $method === 'POST') {
    requireCsrf();
    requireRole('admin', 'executive', 'manager', 'senior_manager', 'super_admin');
    $b  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id'] ?? 0);
    if (!$id) respond(['success' => false, 'error' => 'id is required.'], 400);

    $stmt = $pdo->prepare("SELECT * FROM challenges WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $challenge = $stmt->fetch();
    if (!$challenge) respond(['success' => false, 'error' => 'Challenge not found.'], 404);

    // Only the creator or admin/executive may update
    $adminRoles = ['admin', 'executive', 'super_admin'];
    $isCreator  = (int)$challenge['created_by'] === (int)$user['id'];
    $isPriv     = in_array($user['role'], $adminRoles, true);
    if (!$isCreator && !$isPriv) {
        respond(['success' => false, 'error' => 'Only the creator or an admin/executive can update this challenge.'], 403);
    }

    $title       = trim($b['title'] ?? $challenge['title']);
    $description = array_key_exists('description', $b) ? trim($b['description']) : $challenge['description'];
    $deadline    = array_key_exists('deadline', $b) ? trim($b['deadline']) : $challenge['deadline'];
    $status      = $b['status'] ?? $challenge['status'];

    if ($title === '') respond(['success' => false, 'error' => 'Title cannot be empty.'], 400);

    $allowed_statuses = ['active', 'closed', 'draft'];
    if (!in_array($status, $allowed_statuses, true)) {
        respond(['success' => false, 'error' => 'Invalid status value.'], 400);
    }

    $pdo->prepare(
        "UPDATE challenges SET title = ?, description = ?, deadline = ?, status = ?, updated_at = NOW()
         WHERE id = ?"
    )->execute([$title, $description ?: null, $deadline ?: null, $status, $id]);

    respond(['success' => true]);
}

// ── DELETE a challenge ────────────────────────────────────────────
if ($action === 'delete' && $method === 'POST') {
    requireCsrf();
    requireRole('admin', 'super_admin');
    $b  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id'] ?? 0);
    if (!$id) respond(['success' => false, 'error' => 'id is required.'], 400);

    $stmt = $pdo->prepare("SELECT id FROM challenges WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) respond(['success' => false, 'error' => 'Challenge not found.'], 404);

    // Orphan linked ideas (set challenge_id = NULL) before deletion
    $pdo->prepare("UPDATE ideas SET challenge_id = NULL WHERE challenge_id = ?")->execute([$id]);

    $pdo->prepare("DELETE FROM challenges WHERE id = ?")->execute([$id]);

    respond(['success' => true]);
}

respond(['success' => false, 'error' => 'Unknown action'], 400);
