<?php
require_once __DIR__ . '/config.php';

$user   = requireAuth();
$action = $_GET['action'] ?? '';
$pdo    = db();

// ── 5-star rating stats (legacy) ──────────────────────────────
function voteStats(int $ideaId, int $userId, PDO $pdo): array {
    $s = $pdo->prepare(
        "SELECT COUNT(*) AS vote_count,
                ROUND(AVG(rating), 1) AS avg_rating,
                MAX(CASE WHEN user_id = ? THEN rating ELSE NULL END) AS user_rating
         FROM idea_votes WHERE idea_id = ?"
    );
    $s->execute([$userId, $ideaId]);
    $row = $s->fetch();
    $vc  = (int)($row['vote_count'] ?? 0);
    $ar  = $vc > 0 ? (float)($row['avg_rating'] ?? 0) : 0.0;
    $ur  = ($row['user_rating'] !== null) ? (int)$row['user_rating'] : null;
    return ['vote_count' => $vc, 'avg_rating' => $ar, 'user_rating' => $ur];
}

// ── Community upvote/downvote stats ───────────────────────────
function communityVoteStats(int $ideaId, int $userId, PDO $pdo): array {
    $s = $pdo->prepare(
        "SELECT
            SUM(CASE WHEN vote_type='up'   THEN 1 ELSE 0 END) AS upvotes,
            SUM(CASE WHEN vote_type='down' THEN 1 ELSE 0 END) AS downvotes,
            MAX(CASE WHEN user_id=?        THEN vote_type ELSE NULL END) AS user_vote
         FROM idea_community_votes WHERE idea_id=?"
    );
    $s->execute([$userId, $ideaId]);
    $row = $s->fetch();
    return [
        'upvotes'   => (int)($row['upvotes']   ?? 0),
        'downvotes' => (int)($row['downvotes'] ?? 0),
        'user_vote' => $row['user_vote'] ?? null,
    ];
}

// ── Compute community-adjusted score ─────────────────────────
function communityAdjustedScore(int $aiScore, int $upvotes, int $downvotes): int {
    $net        = $upvotes - $downvotes;
    $adjustment = max(-20, min(20, $net * 3));
    return max(0, min(100, $aiScore + $adjustment));
}

// ── POST ?action=vote  {idea_id, rating} ──────────────────────
if ($action === 'vote' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $b      = json_decode(file_get_contents('php://input'), true) ?? [];
    $ideaId = (int)($b['idea_id'] ?? 0);
    $rating = (int)($b['rating']  ?? 0);

    if (!$ideaId || $rating < 1 || $rating > 5) {
        respond(['success' => false, 'error' => 'Invalid request — idea_id and rating (1–5) required.'], 400);
    }

    $row = $pdo->prepare("SELECT submitter_id FROM ideas WHERE id = ?");
    $row->execute([$ideaId]);
    $idea = $row->fetch();
    if (!$idea) respond(['success' => false, 'error' => 'Idea not found.'], 404);
    if ((int)$idea['submitter_id'] === (int)$user['id']) {
        respond(['success' => false, 'error' => 'You cannot vote on your own idea.'], 403);
    }

    $pdo->prepare(
        "INSERT INTO idea_votes (idea_id, user_id, rating)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE rating = ?, updated_at = NOW()"
    )->execute([$ideaId, $user['id'], $rating, $rating]);

    respond(array_merge(['success' => true], voteStats($ideaId, $user['id'], $pdo)));
}

// ── POST ?action=upvote|downvote  {idea_id} ───────────────────
if (in_array($action, ['upvote', 'downvote'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $b        = json_decode(file_get_contents('php://input'), true) ?? [];
    $ideaId   = (int)($b['idea_id'] ?? 0);
    $voteType = $action === 'upvote' ? 'up' : 'down';

    if (!$ideaId) respond(['success' => false, 'error' => 'Invalid idea_id.'], 400);

    $row = $pdo->prepare("SELECT submitter_id, ai_score FROM ideas WHERE id=?");
    $row->execute([$ideaId]);
    $idea = $row->fetch();
    if (!$idea) respond(['success' => false, 'error' => 'Idea not found.'], 404);
    if ((int)$idea['submitter_id'] === (int)$user['id']) {
        respond(['success' => false, 'error' => 'You cannot vote on your own idea.'], 403);
    }

    // Check existing vote
    $chk = $pdo->prepare("SELECT vote_type FROM idea_community_votes WHERE idea_id=? AND user_id=?");
    $chk->execute([$ideaId, $user['id']]);
    $existing = $chk->fetchColumn();

    if ($existing === false) {
        // No prior vote — insert
        $pdo->prepare("INSERT INTO idea_community_votes (idea_id, user_id, vote_type) VALUES (?,?,?)")
            ->execute([$ideaId, $user['id'], $voteType]);
        $col = $voteType === 'up' ? 'upvotes' : 'downvotes';
        $pdo->prepare("UPDATE ideas SET {$col} = {$col} + 1 WHERE id=?")->execute([$ideaId]);

    } elseif ($existing === $voteType) {
        // Same vote again — toggle off (remove)
        $pdo->prepare("DELETE FROM idea_community_votes WHERE idea_id=? AND user_id=?")
            ->execute([$ideaId, $user['id']]);
        $col = $voteType === 'up' ? 'upvotes' : 'downvotes';
        $pdo->prepare("UPDATE ideas SET {$col} = GREATEST(0, {$col} - 1) WHERE id=?")->execute([$ideaId]);

    } else {
        // Switching vote direction
        $pdo->prepare("UPDATE idea_community_votes SET vote_type=? WHERE idea_id=? AND user_id=?")
            ->execute([$voteType, $ideaId, $user['id']]);
        $oldCol = $existing === 'up' ? 'upvotes' : 'downvotes';
        $newCol = $voteType  === 'up' ? 'upvotes' : 'downvotes';
        $pdo->prepare("UPDATE ideas SET {$oldCol}=GREATEST(0,{$oldCol}-1), {$newCol}={$newCol}+1 WHERE id=?")
            ->execute([$ideaId]);
    }

    $stats        = communityVoteStats($ideaId, $user['id'], $pdo);
    $communityScr = communityAdjustedScore((int)$idea['ai_score'], $stats['upvotes'], $stats['downvotes']);

    respond(array_merge(['success' => true, 'community_score' => $communityScr], $stats));
}

// ── GET ?action=community_stats&idea_id=X ────────────────────
if ($action === 'community_stats') {
    $ideaId = (int)($_GET['idea_id'] ?? 0);
    if (!$ideaId) respond(['success' => false, 'error' => 'Missing idea_id.'], 400);

    $aiRow = $pdo->prepare("SELECT ai_score FROM ideas WHERE id=?");
    $aiRow->execute([$ideaId]);
    $aiScore = (int)($aiRow->fetchColumn() ?: 0);

    $stats        = communityVoteStats($ideaId, $user['id'], $pdo);
    $communityScr = communityAdjustedScore($aiScore, $stats['upvotes'], $stats['downvotes']);
    respond(array_merge(['success' => true, 'community_score' => $communityScr], $stats));
}

// ── GET ?action=poll_all — lightweight real-time update ───────
if ($action === 'poll_all') {
    $stmt = $pdo->query(
        "SELECT icv.idea_id,
                SUM(CASE WHEN icv.vote_type='up'   THEN 1 ELSE 0 END) AS upvotes,
                SUM(CASE WHEN icv.vote_type='down' THEN 1 ELSE 0 END) AS downvotes,
                i.ai_score
         FROM idea_community_votes icv
         JOIN ideas i ON i.id = icv.idea_id
         GROUP BY icv.idea_id, i.ai_score"
    );
    $rows   = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $r) {
        $up  = (int)$r['upvotes'];
        $dn  = (int)$r['downvotes'];
        $result[(int)$r['idea_id']] = [
            'upvotes'         => $up,
            'downvotes'       => $dn,
            'community_score' => communityAdjustedScore((int)$r['ai_score'], $up, $dn),
        ];
    }
    respond(['success' => true, 'votes' => $result, 'ts' => time()]);
}

// ── GET ?action=stats&idea_id=X ──────────────────────────────
if ($action === 'stats') {
    $ideaId = (int)($_GET['idea_id'] ?? 0);
    if (!$ideaId) respond(['success' => false, 'error' => 'Missing idea_id.'], 400);
    respond(array_merge(['success' => true], voteStats($ideaId, $user['id'], $pdo)));
}

respond(['success' => false, 'error' => 'Unknown action.'], 400);
