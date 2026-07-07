<?php
// api/comments.php  –  Comment threads on ideas
// GET  ?action=list&idea_id=X   — top-level comments + replies
// POST ?action=add              — add a comment
// POST ?action=delete           — soft- or hard-delete a comment
require_once __DIR__ . '/config.php';

$user   = requireAuth();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$pdo    = db();

// ── LIST comments for an idea ─────────────────────────────────────
if ($action === 'list' && $method === 'GET') {
    $ideaId = (int)($_GET['idea_id'] ?? 0);
    if (!$ideaId) respond(['success' => false, 'error' => 'idea_id is required.'], 400);

    // Fetch all non-deleted top-level + replies in one query; filter in PHP
    $stmt = $pdo->prepare(
        "SELECT c.id, c.idea_id, c.parent_id, c.content, c.is_deleted, c.created_at,
                u.id AS user_id, u.name AS user_name, u.avatar_initials, u.role AS user_role
         FROM idea_comments c
         LEFT JOIN users u ON u.id = c.user_id
         WHERE c.idea_id = ?
         ORDER BY c.created_at ASC"
    );
    $stmt->execute([$ideaId]);
    $rows = $stmt->fetchAll();

    // Check which deleted comments have replies (so we keep as placeholder)
    $childParentIds = [];
    foreach ($rows as $r) {
        if ($r['parent_id']) {
            $childParentIds[(int)$r['parent_id']] = true;
        }
    }

    // Build indexed map for nesting
    $commentMap = [];
    foreach ($rows as &$r) {
        $r['replies'] = [];
        if ((int)$r['is_deleted'] === 1) {
            // If it has replies keep as placeholder, else hide entirely
            if (!isset($childParentIds[(int)$r['id']])) {
                continue; // skip — will not add to map
            }
            // Replace content with placeholder
            $r['content']          = '[deleted]';
            $r['user_name']        = null;
            $r['avatar_initials']  = null;
            $r['user_role']        = null;
        }
        $commentMap[(int)$r['id']] = $r;
    }
    unset($r);

    // Nest replies under parents
    $topLevel = [];
    foreach ($commentMap as $id => &$comment) {
        $pid = (int)($comment['parent_id'] ?? 0);
        if ($pid && isset($commentMap[$pid])) {
            $commentMap[$pid]['replies'][] = &$comment;
        } else {
            $topLevel[] = &$comment;
        }
    }
    unset($comment);

    respond(['success' => true, 'comments' => array_values($topLevel)]);
}

// ── ADD a comment ─────────────────────────────────────────────────
if ($action === 'add' && $method === 'POST') {
    requireCsrf();
    $b        = json_decode(file_get_contents('php://input'), true) ?? [];
    $ideaId   = (int)($b['idea_id'] ?? 0);
    $content  = trim($b['content'] ?? '');
    $parentId = !empty($b['parent_id']) ? (int)$b['parent_id'] : null;

    if (!$ideaId) respond(['success' => false, 'error' => 'idea_id is required.'], 400);
    if ($content === '') respond(['success' => false, 'error' => 'Comment content cannot be empty.'], 400);
    if (mb_strlen($content) > 1000) respond(['success' => false, 'error' => 'Comment cannot exceed 1000 characters.'], 400);

    // Verify idea exists
    $ideaCheck = $pdo->prepare("SELECT id FROM ideas WHERE id = ? LIMIT 1");
    $ideaCheck->execute([$ideaId]);
    if (!$ideaCheck->fetch()) respond(['success' => false, 'error' => 'Idea not found.'], 404);

    // If replying, verify parent comment exists and belongs to same idea
    if ($parentId !== null) {
        $parentCheck = $pdo->prepare("SELECT id FROM idea_comments WHERE id = ? AND idea_id = ? LIMIT 1");
        $parentCheck->execute([$parentId, $ideaId]);
        if (!$parentCheck->fetch()) respond(['success' => false, 'error' => 'Parent comment not found.'], 404);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO idea_comments (idea_id, user_id, content, parent_id, is_deleted, created_at)
         VALUES (?, ?, ?, ?, 0, NOW())"
    );
    $stmt->execute([$ideaId, $user['id'], $content, $parentId]);
    $commentId = (int)$pdo->lastInsertId();

    respond(['success' => true, 'comment_id' => $commentId]);
}

// ── DELETE a comment ──────────────────────────────────────────────
if ($action === 'delete' && $method === 'POST') {
    requireCsrf();
    $b  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id'] ?? 0);
    if (!$id) respond(['success' => false, 'error' => 'Comment id is required.'], 400);

    $stmt = $pdo->prepare("SELECT * FROM idea_comments WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $comment = $stmt->fetch();
    if (!$comment) respond(['success' => false, 'error' => 'Comment not found.'], 404);

    $adminRoles = ['admin', 'executive', 'super_admin'];
    $isOwner    = (int)$comment['user_id'] === (int)$user['id'];
    $isPriv     = in_array($user['role'], $adminRoles, true);

    if (!$isOwner && !$isPriv) {
        respond(['success' => false, 'error' => 'You do not have permission to delete this comment.'], 403);
    }

    // Check if this comment has replies
    $replyCount = $pdo->prepare("SELECT COUNT(*) FROM idea_comments WHERE parent_id = ?");
    $replyCount->execute([$id]);
    $hasReplies = (int)$replyCount->fetchColumn() > 0;

    if ($hasReplies) {
        // Soft-delete: keep row, mark deleted, clear content
        $pdo->prepare("UPDATE idea_comments SET is_deleted = 1, content = '' WHERE id = ?")
            ->execute([$id]);
    } else {
        // Hard-delete: no children, safe to remove
        $pdo->prepare("DELETE FROM idea_comments WHERE id = ?")->execute([$id]);
    }

    respond(['success' => true]);
}

respond(['success' => false, 'error' => 'Unknown action'], 400);
