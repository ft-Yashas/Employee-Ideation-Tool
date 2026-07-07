<?php
// api/ideas.php  –  RESTful endpoint for ideas
// GET    ?action=list|get&id=X|my|review|dashboard
// POST   ?action=submit|draft|review_action
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/score.php';
require_once __DIR__ . '/mailer.php';

// ── Load approval workflow config from org_settings ───────────────
function getApprovalConfig(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $settings = getOrgSettings();
    $mode     = $settings['approval_mode'] ?? 'default';
    if ($mode !== 'custom') {
        $cfg = [
            'mode'           => 'default',
            'reviewer_roles' => ['team_lead','project_lead','manager','senior_manager'],
            'final_roles'    => ['executive','admin','super_admin'],
            'threshold'      => (int)($settings['approval_threshold'] ?? 100),
        ];
        return $cfg;
    }
    $reviewerRoles = array_filter(array_map('trim', explode(',', $settings['approval_reviewer_roles'] ?? '')));
    $finalRoles    = array_filter(array_map('trim', explode(',', $settings['approval_final_approver_roles'] ?? '')));
    if (empty($reviewerRoles)) $reviewerRoles = ['team_lead','project_lead','manager','senior_manager'];
    if (empty($finalRoles))    $finalRoles    = ['executive','admin','super_admin'];
    $cfg = [
        'mode'           => 'custom',
        'reviewer_roles' => $reviewerRoles,
        'final_roles'    => $finalRoles,
        'threshold'      => max(1, min(100, (int)($settings['approval_threshold'] ?? 100))),
    ];
    return $cfg;
}

$user   = requireAuth();
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

// ── LIST all ideas ─────────────────────────────────────────────────
if ($action === 'list') {
    $where  = [];
    $params = [];

    // Submitters see own; team leads/managers see direct reports; executive/admin see all
    $individualRoles = ['trainee', 'employee'];
    $teamRoles       = ['team_lead', 'project_lead', 'manager', 'senior_manager'];
    if (in_array($user['role'], $individualRoles, true)) {
        $where[]  = '(i.submitter_id = ? OR i.co_suggester_1_id = ? OR i.co_suggester_2_id = ?)';
        $params   = array_merge($params, [$user['id'], $user['id'], $user['id']]);
    } elseif (in_array($user['role'], $teamRoles, true)) {
        $where[]  = '(i.submitter_id IN (SELECT id FROM users WHERE manager_id = ?) OR i.submitter_id = ?)';
        $params   = array_merge($params, [$user['id'], $user['id']]);
    }

    if (!empty($_GET['status'])) {
        $where[]  = 'i.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['search'])) {
        $where[]  = '(i.title LIKE ? OR i.idea_code LIKE ?)';
        $s        = '%' . $_GET['search'] . '%';
        $params   = array_merge($params, [$s, $s]);
    }
    if (!empty($_GET['impact'])) {
        $where[]  = 'i.impact_level = ?';
        $params[] = $_GET['impact'];
    }

    $uid = (int)$user['id'];
    // Prepend uid to params for the community-vote subquery
    $params_list = array_merge([$uid], $params);
    $sql = "SELECT i.*, u.name AS submitter_name, u.department, u.avatar_initials,
                   c1.name AS co1_name, c2.name AS co2_name,
                   (SELECT COUNT(*) FROM idea_votes WHERE idea_id=i.id) AS vote_count,
                   (SELECT ROUND(AVG(rating),1) FROM idea_votes WHERE idea_id=i.id) AS avg_rating,
                   (SELECT vote_type FROM idea_community_votes WHERE idea_id=i.id AND user_id=?) AS user_community_vote
            FROM ideas i
            JOIN users u ON u.id = i.submitter_id
            LEFT JOIN users c1 ON c1.id = i.co_suggester_1_id
            LEFT JOIN users c2 ON c2.id = i.co_suggester_2_id"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY i.updated_at DESC LIMIT 100";

    $stmt = db()->prepare($sql);
    $stmt->execute($params_list);
    $ideas = $stmt->fetchAll();
    foreach ($ideas as &$idea) {
        $idea['submitter_name'] = esc($idea['submitter_name'] ?? '');
        $idea['co1_name']      = esc($idea['co1_name'] ?? '');
        $idea['co2_name']       = esc($idea['co2_name'] ?? '');
    }
    unset($idea);

    // Mask anonymous submitters for non-privileged users
    $canSeeAnon = in_array($user['role'], ['manager','senior_manager','executive','admin','super_admin'], true);
    if (!$canSeeAnon) {
        foreach ($ideas as &$idea) {
            if (!empty($idea['is_anonymous'])) {
                $idea['submitter_name']  = 'Anonymous';
                $idea['avatar_initials'] = '?';
                $idea['department']      = '—';
            }
        }
        unset($idea);
    }

    respond(['success' => true, 'ideas' => $ideas]);
}

// ── MY ideas ──────────────────────────────────────────────────────
if ($action === 'my') {
    $uid = (int)$user['id'];
    $stmt = db()->prepare(
        "SELECT i.*, c1.name AS co1_name, c2.name AS co2_name,
                (SELECT COUNT(*) FROM idea_votes WHERE idea_id=i.id) AS vote_count,
                (SELECT ROUND(AVG(rating),1) FROM idea_votes WHERE idea_id=i.id) AS avg_rating,
                (SELECT vote_type FROM idea_community_votes WHERE idea_id=i.id AND user_id=?) AS user_community_vote
         FROM ideas i
         LEFT JOIN users c1 ON c1.id = i.co_suggester_1_id
         LEFT JOIN users c2 ON c2.id = i.co_suggester_2_id
         WHERE i.submitter_id = ? OR i.co_suggester_1_id = ? OR i.co_suggester_2_id = ?
         ORDER BY i.updated_at DESC"
    );
    $stmt->execute([$uid, $uid, $uid, $uid]);
    $ideas = $stmt->fetchAll();
    foreach ($ideas as &$idea) {
        $idea['co1_name'] = esc($idea['co1_name'] ?? '');
        $idea['co2_name'] = esc($idea['co2_name'] ?? '');
    }
    unset($idea);
    respond(['success' => true, 'ideas' => $ideas]);
}

// ── REVIEW QUEUE ──────────────────────────────────────────────────────
$reviewerRoles = ['team_lead','project_lead','manager','senior_manager','executive','admin','super_admin'];
if ($action === 'review') {
    requireRole(...$reviewerRoles);
    $pdo = db();
    $uid = (int)$user['id'];

    $cols = "SELECT DISTINCT i.*, u.name AS submitter_name, u.department, u.avatar_initials,
                    ir.decision AS my_reviewer_decision,
                    (SELECT COUNT(*) FROM idea_votes WHERE idea_id=i.id) AS vote_count,
                    (SELECT ROUND(AVG(rating),1) FROM idea_votes WHERE idea_id=i.id) AS avg_rating,
                    (SELECT COUNT(*) FROM idea_reviewers WHERE idea_id=i.id) AS reviewer_count,
                    (SELECT COUNT(*) FROM idea_reviewers WHERE idea_id=i.id AND decision='approved') AS approved_count,
                    (SELECT COUNT(*) FROM idea_reviewers WHERE idea_id=i.id AND decision='rejected') AS rejected_count,
                    (SELECT vote_type FROM idea_community_votes WHERE idea_id=i.id AND user_id=?) AS user_community_vote
             FROM ideas i
             JOIN users u ON u.id = i.submitter_id
             LEFT JOIN idea_reviewers ir ON ir.idea_id = i.id AND ir.reviewer_id = ?";

    $cfg = getApprovalConfig();
    $teamRoles = $cfg['reviewer_roles'];
    if (in_array($user['role'], $teamRoles, true)) {
        // Show ideas where I am the current_reviewer OR submitter's manager (legacy)
        // LEFT JOIN already filtered ir.reviewer_id=? so WHERE condition is redundant
        $stmt = $pdo->prepare($cols .
            " WHERE i.status IN ('Submitted','Under Review')
              AND (i.workflow_type = 'hierarchical'
                   AND (i.current_reviewer_id = ? OR (i.current_reviewer_id IS NULL AND u.manager_id = ?))
                   OR i.workflow_type = 'multi_reviewer' AND ir.decision = 'pending')
              ORDER BY i.review_due_date ASC, i.ai_score DESC, i.submitted_at ASC");
        $stmt->execute([$uid, $uid, $uid, $uid]);
    } else {
        // Admin / exec / super_admin — see all non-draft ideas
        // Remove the ir.reviewer_id condition from the LEFT JOIN since we're not filtering by it
        $stmt = $pdo->prepare(
            "SELECT DISTINCT i.*, u.name AS submitter_name, u.department, u.avatar_initials,
                    (SELECT COUNT(*) FROM idea_votes WHERE idea_id=i.id) AS vote_count,
                    (SELECT ROUND(AVG(rating),1) FROM idea_votes WHERE idea_id=i.id) AS avg_rating,
                    (SELECT COUNT(*) FROM idea_reviewers WHERE idea_id=i.id) AS reviewer_count,
                    (SELECT COUNT(*) FROM idea_reviewers WHERE idea_id=i.id AND decision='approved') AS approved_count,
                    (SELECT COUNT(*) FROM idea_reviewers WHERE idea_id=i.id AND decision='rejected') AS rejected_count,
                    (SELECT vote_type FROM idea_community_votes WHERE idea_id=i.id AND user_id=?) AS user_community_vote
             FROM ideas i
             JOIN users u ON u.id = i.submitter_id
             WHERE i.status IN ('Submitted','Under Review')
              ORDER BY i.review_due_date ASC, i.ai_score DESC, i.submitted_at ASC");
        $stmt->execute([$uid]);
    }
    $ideas = $stmt->fetchAll();
    foreach ($ideas as &$idea) { $idea['submitter_name'] = esc($idea['submitter_name'] ?? ''); }
    unset($idea);
    respond(['success' => true, 'ideas' => $ideas]);
}

// ── GET single idea detail ─────────────────────────────────────────
if ($action === 'get') {
    $id   = (int)($_GET['id'] ?? 0);
    $uid  = (int)$user['id'];
    $stmt = db()->prepare(
        "SELECT i.*, u.name AS submitter_name, u.department, u.business_unit,
                u.avatar_initials, u.email AS submitter_email,
                c1.name AS co1_name, c2.name AS co2_name,
                m.name AS manager_name,
                (SELECT COUNT(*) FROM idea_votes WHERE idea_id=i.id) AS vote_count,
                (SELECT ROUND(AVG(rating),1) FROM idea_votes WHERE idea_id=i.id) AS avg_rating,
                (SELECT vote_type FROM idea_community_votes WHERE idea_id=i.id AND user_id=?) AS user_community_vote
         FROM ideas i
         JOIN  users u  ON u.id  = i.submitter_id
         LEFT JOIN users c1 ON c1.id = i.co_suggester_1_id
         LEFT JOIN users c2 ON c2.id = i.co_suggester_2_id
         LEFT JOIN users m  ON m.id  = u.manager_id
         WHERE i.id = ?"
    );
    $stmt->execute([$uid, $id]);
    $idea = $stmt->fetch();
    if (!$idea) respond(['success' => false, 'error' => 'Idea not found'], 404);
    // Escape all user-provided name fields to prevent XSS
    $idea['submitter_name'] = esc($idea['submitter_name'] ?? '');
    $idea['co1_name']      = esc($idea['co1_name'] ?? '');
    $idea['co2_name']       = esc($idea['co2_name'] ?? '');
    $idea['manager_name']   = esc($idea['manager_name'] ?? '');

    // Attachments
    $att = db()->prepare("SELECT * FROM idea_attachments WHERE idea_id = ?");
    $att->execute([$id]);
    $idea['attachments'] = $att->fetchAll();

    // Workflow timeline
    $wf = db()->prepare(
        "SELECT w.*, u.name AS actor_name, u.role AS actor_role
         FROM idea_workflow w JOIN users u ON u.id = w.actor_id
         WHERE w.idea_id = ? ORDER BY w.created_at ASC"
    );
    $wf->execute([$id]);
    $wfData = $wf->fetchAll();
    foreach ($wfData as &$w) { $w['actor_name'] = esc($w['actor_name'] ?? ''); }
    unset($w);
    $idea['workflow'] = $wfData;

    // Reviewer assignments (multi-reviewer workflow)
    try {
        $rv = db()->prepare(
            "SELECT ir.*, u.name AS reviewer_name, u.role AS reviewer_role,
                    u.avatar_initials, u.department
             FROM idea_reviewers ir
             JOIN users u ON u.id = ir.reviewer_id
             WHERE ir.idea_id = ? ORDER BY ir.assigned_at ASC"
        );
        $rv->execute([$id]);
        $revData = $rv->fetchAll();
        foreach ($revData as &$r) {
            $r['reviewer_name'] = esc($r['reviewer_name'] ?? '');
        }
        unset($r);
        $idea['reviewers'] = $revData;
    } catch (Exception $e) {
        $idea['reviewers'] = [];
    }

    // Mask anonymous submitter for non-privileged roles (own idea always visible to submitter)
    $canSeeAnon = in_array($user['role'], ['manager','senior_manager','executive','admin','super_admin'], true);
    if (!$canSeeAnon && !empty($idea['is_anonymous']) && (int)$idea['submitter_id'] !== (int)$user['id']) {
        $idea['submitter_name']  = 'Anonymous';
        $idea['submitter_email'] = null;
        $idea['avatar_initials'] = '?';
        $idea['department']      = '—';
        $idea['business_unit']   = '—';
        $idea['manager_name']    = null;
    }

    respond(['success' => true, 'idea' => $idea]);
}

// ── SUBMIT / SAVE DRAFT ───────────────────────────────────────────
if (in_array($action, ['submit', 'draft'], true) && $method === 'POST') {
    requireCsrf();
    $b = json_decode(file_get_contents('php://input'), true) ?? [];

    $title    = trim($b['title'] ?? '');
    $sit      = trim($b['present_situation'] ?? '');
    $sol      = trim($b['proposed_solution'] ?? '');
    $impacts  = trim($b['impact_areas'] ?? '');
    $impLvl   = $b['impact_level'] ?? 'Medium';
    $tangible = trim($b['tangible_benefit'] ?? '');
    $intang   = trim($b['intangible_benefit'] ?? '');
    $co1          = !empty($b['co_suggester_1_id']) ? (int)$b['co_suggester_1_id'] : null;
    $co2          = !empty($b['co_suggester_2_id']) ? (int)$b['co_suggester_2_id'] : null;
    $editId       = !empty($b['id']) ? (int)$b['id'] : null;
    $isAnon       = !empty($b['is_anonymous']) ? 1 : 0;
    $challengeId  = !empty($b['challenge_id']) ? (int)$b['challenge_id'] : null;
    $templateType = trim($b['template_type'] ?? '') ?: null;

    if (!$title || !$sit || !$sol) {
        respond(['success' => false, 'error' => 'Title, present situation and proposed solution are required.'], 400);
    }

    // Compute AI score from submitted data
    $ideaData = [
        'title'              => $title,
        'present_situation'  => $sit,
        'proposed_solution'  => $sol,
        'impact_areas'       => $impacts,
        'impact_level'       => $impLvl,
        'tangible_benefit'   => $tangible,
        'intangible_benefit' => $intang,
        'co_suggester_1_id'  => $co1,
        'co_suggester_2_id'  => $co2,
    ];
    $ai = computeAIScoreWithReason($ideaData);
    $aiScore = $ai['score'];
    $aiReason = $ai['reason'];
    
     // If editing an existing idea, ensure user has permission

    $status      = $action === 'submit' ? 'Submitted' : 'Draft';
    $submittedAt = $action === 'submit' ? date('Y-m-d H:i:s') : null;
    $pdo         = db();

    // SLA due date + initial reviewer set only on first submission
    $reviewDueDate     = null;
    $currentReviewerId = null;
    if ($action === 'submit') {
        $slaDays = 7;
        try {
            $slaSt = $pdo->prepare("SELECT value FROM org_settings WHERE key_name='review_sla_days' LIMIT 1");
            $slaSt->execute();
            $slaVal = $slaSt->fetchColumn();
            if ($slaVal !== false) $slaDays = max(1, (int)$slaVal);
        } catch (Exception $e) {}
        $reviewDueDate     = date('Y-m-d', strtotime("+{$slaDays} days"));
        $currentReviewerId = $user['manager_id'] ?? null;
    }

    // Check prior status before updating so we only award points on first submission
    $wasAlreadySubmitted = false;
    if ($editId && $action === 'submit') {
        $chk = $pdo->prepare("SELECT status FROM ideas WHERE id=? AND submitter_id=?");
        $chk->execute([$editId, $user['id']]);
        $prevStatus = $chk->fetchColumn();
        $wasAlreadySubmitted = ($prevStatus !== false && $prevStatus !== 'Draft');
    }

    if ($editId) {
        $pdo->prepare(
            "UPDATE ideas SET
            title=?,present_situation=?,proposed_solution=?,
            impact_areas=?,impact_level=?,tangible_benefit=?,intangible_benefit=?,
            co_suggester_1_id=?,co_suggester_2_id=?,
            is_anonymous=?,challenge_id=?,template_type=?,
            status=?,submitted_at=COALESCE(submitted_at,?),
            review_due_date=COALESCE(review_due_date,?),
            current_reviewer_id=COALESCE(current_reviewer_id,?),
            ai_score=?,ai_reason=?,
            updated_at=NOW()
            WHERE id=? AND submitter_id=?"
        )->execute([
            $title,$sit,$sol,$impacts,$impLvl,$tangible,$intang,
            $co1,$co2,$isAnon,$challengeId,$templateType,
            $status,$submittedAt,$reviewDueDate,$currentReviewerId,
            $aiScore,$aiReason,
            $editId,$user['id']
        ]);
        $ideaId = $editId;
    } else {
        $code = generateIdeaCode();
        $pdo->prepare(
            "INSERT INTO ideas (
                idea_code,title,present_situation,proposed_solution,
                impact_areas,impact_level,tangible_benefit,intangible_benefit,
                co_suggester_1_id,co_suggester_2_id,is_anonymous,challenge_id,template_type,
                status,submitter_id,submitted_at,review_due_date,current_reviewer_id,
                ai_score,ai_reason
                )
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $code,$title,$sit,$sol,$impacts,$impLvl,$tangible,$intang,
            $co1,$co2,$isAnon,$challengeId,$templateType,
            $status,$user['id'],$submittedAt,$reviewDueDate,$currentReviewerId,
            $aiScore,$aiReason
        ]);
        $ideaId = (int)$pdo->lastInsertId();
    }

    if ($action === 'submit' && !$wasAlreadySubmitted) {
        addWorkflow($ideaId, $user['id'], 'Submitted');
        addPoints($user['id'], POINTS_SUBMIT);
        $_SESSION['user']['points'] += POINTS_SUBMIT;

        // Notify manager
        if ($user['manager_id']) {
            addNotification(
                $user['manager_id'],
                'New Idea Submitted',
                $user['name'] . ' submitted a new idea. Please review it in your queue.',
                $ideaId
            );
            $mgrQ = $pdo->prepare("SELECT email, name FROM users WHERE id=?");
            $mgrQ->execute([$user['manager_id']]);
            $mgr = $mgrQ->fetch();
            if ($mgr && $mgr['email']) {
                queueEmail($mgr['email'], $mgr['name'],
                    'New Idea Requires Your Review',
                    "Dear {$mgr['name']},\n\n{$user['name']} has submitted a new idea for your review.\n\nPlease log in to action it from your review queue.");
            }
        }
    }

    $stmt = $pdo->prepare("SELECT idea_code FROM ideas WHERE id=?");
    $stmt->execute([$ideaId]);
    $row  = $stmt->fetch();

    respond([
        'success'      => true,
        'idea_id'      => $ideaId,
        'idea_code'    => $row['idea_code'],
        'ai_score'     => $aiScore,
        'points_added' => ($action === 'submit' && !$wasAlreadySubmitted) ? POINTS_SUBMIT : 0,
    ]);
}

// ── MANAGER REVIEW (approve / reject / implement) ─────────────────
if ($action === 'review_action' && $method === 'POST') {
    requireCsrf();
    requireRole('team_lead','project_lead','manager','senior_manager','admin','executive','super_admin');
    $b       = json_decode(file_get_contents('php://input'), true) ?? [];
    $ideaId  = (int)($b['idea_id'] ?? 0);
    $decision= $b['decision'] ?? '';   // Approved | Rejected | Implemented | Under Review
    $comment = trim($b['comment'] ?? '');

    if (!$ideaId || !in_array($decision, ['Approved','Rejected','Implemented','Under Review'], true)) {
        respond(['success' => false, 'error' => 'Invalid request.'], 400);
    }

    $pdo  = db();
    $stmt = $pdo->prepare("SELECT * FROM ideas WHERE id=?");
    $stmt->execute([$ideaId]);
    $idea = $stmt->fetch();
    if (!$idea) respond(['success' => false, 'error' => 'Idea not found.'], 404);

    // Prevent self-approval
    if ((int)$idea['submitter_id'] === (int)$user['id']) {
        respond(['success' => false, 'error' => 'You cannot review or approve your own idea.'], 403);
    }

    $wfAction = match($decision) {
        'Approved'    => 'Approved',
        'Rejected'    => 'Rejected',
        'Implemented' => 'Implemented',
        default       => 'Reviewed',
    };

    // Idempotency guard — prevent duplicate workflow entries within 10 s
    $dupCheck = $pdo->prepare(
        "SELECT COUNT(*) FROM idea_workflow WHERE idea_id=? AND actor_id=? AND action=? AND created_at > NOW() - INTERVAL 10 SECOND"
    );
    $dupCheck->execute([$ideaId, $user['id'], $wfAction]);
    if ((int)$dupCheck->fetchColumn() > 0) {
        respond(['success' => false, 'error' => 'Duplicate action detected. Please wait a moment before retrying.'], 429);
    }

    // Escalation chain — loaded from org_settings when custom mode is active
    $cfg = getApprovalConfig();
    $escalationRoles    = $cfg['reviewer_roles'];
    $finalApproverRoles = $cfg['final_roles'];

    if ($decision === 'Approved'
        && ($idea['workflow_type'] ?? 'hierarchical') !== 'multi_reviewer'
        && in_array($user['role'], $escalationRoles, true)
    ) {
        $mgrStmt = $pdo->prepare(
            "SELECT u2.id, u2.name, u2.role, u2.email
             FROM users u1 JOIN users u2 ON u2.id = u1.manager_id
             WHERE u1.id = ? LIMIT 1"
        );
        $mgrStmt->execute([$user['id']]);
        $nextReviewer = $mgrStmt->fetch();

        $reviewerPool = array_merge($escalationRoles, $finalApproverRoles);
        if ($nextReviewer && in_array($nextReviewer['role'], $reviewerPool, true)) {
            $lvl = ((int)($idea['escalation_level'] ?? 0)) + 1;
            $pdo->prepare(
                "UPDATE ideas SET status='Under Review', current_reviewer_id=?,
                 escalation_level=?, updated_at=NOW() WHERE id=?"
            )->execute([$nextReviewer['id'], $lvl, $ideaId]);

            addWorkflow($ideaId, $user['id'], 'Approved',
                trim(($comment ? $comment . ' ' : '') . "[L{$lvl} Approved — escalated to {$nextReviewer['name']}]"));

            addNotification($nextReviewer['id'], 'Idea Escalated for Review',
                "Idea {$idea['idea_code']} — \"{$idea['title']}\" — approved at level {$lvl} and escalated to you for final decision.",
                $ideaId);

            if (!empty($nextReviewer['email'])) {
                queueEmail($nextReviewer['email'], $nextReviewer['name'],
                    "Action Required: Idea {$idea['idea_code']} Escalated to You",
                    "Dear {$nextReviewer['name']},\n\n" .
                    "Idea \"{$idea['title']}\" ({$idea['idea_code']}) has been approved at level {$lvl} and escalated to you for final decision.\n\n" .
                    "Please log in to take action.");
            }

            respond(['success' => true, 'decision' => 'Escalated',
                     'escalated_to' => $nextReviewer['name'], 'points_awarded' => 0]);
        }
        // No higher reviewer found — fall through to final Approved
    }

    $pdo->prepare("UPDATE ideas SET status=?,updated_at=NOW() WHERE id=?")
        ->execute([$decision, $ideaId]);

    // Reload idea to ensure idea_code is available (e.g. if it was just created from draft)
    $codeStmt = $pdo->prepare("SELECT idea_code FROM ideas WHERE id=?");
    $codeStmt->execute([$ideaId]);
    $ideaCode = $codeStmt->fetchColumn() ?: ('#' . $ideaId);

    addWorkflow($ideaId, $user['id'], $wfAction, $comment ?: null);

    // Award points to submitter
    $pts = match($decision) {
        'Approved'    => POINTS_APPROVED,
        'Implemented' => POINTS_IMPLEMENTED,
        default       => 0,
    };
    if ($pts > 0) {
        addPoints($idea['submitter_id'], $pts);
        $pdo->prepare("UPDATE ideas SET points_awarded = points_awarded + ? WHERE id=?")
            ->execute([$pts, $ideaId]);
    }

    // Notify submitter
    $msg = match($decision) {
        'Approved'    => "Your idea {$ideaCode} was Approved." . ($pts > 0 ? " +{$pts} points awarded." : ""),
        'Rejected'    => "Your idea {$ideaCode} was Rejected." . ($comment ? " Feedback: {$comment}" : ""),
        'Implemented' => "Your idea {$ideaCode} is now Implemented." . ($pts > 0 ? " +{$pts} points awarded." : ""),
        default       => "Your idea {$ideaCode} is Under Review.",
    };
    addNotification($idea['submitter_id'], "Idea {$decision}", $msg, $ideaId);

    // Queue email to submitter
    $subStmt = $pdo->prepare("SELECT email, name FROM users WHERE id=?");
    $subStmt->execute([$idea['submitter_id']]);
    $sub = $subStmt->fetch();
    if ($sub && $sub['email']) {
        $subject = "Your Idea {$ideaCode} — {$decision}";
        queueEmail($sub['email'], $sub['name'], $subject, $msg);
    }

    respond(['success' => true, 'decision' => $decision, 'points_awarded' => $pts]);
}

// ── DASHBOARD stats ───────────────────────────────────────────────
if ($action === 'dashboard') {
    $pdo  = db();
    $uid  = $user['id'];
    $role = $user['role'];

    $individualRoles = ['trainee','employee'];
    $teamRoles       = ['team_lead','project_lead','manager','senior_manager'];
    $adminRoles      = ['executive','admin','super_admin'];

    if (in_array($role, $individualRoles, true)) {
        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM ideas WHERE submitter_id=?");
        $totalStmt->execute([$uid]);
    } else {
        $totalStmt = $pdo->query("SELECT COUNT(*) FROM ideas WHERE status != 'Draft'");
    }
    $total = (int)$totalStmt->fetchColumn();

    $counts = [];
    foreach (['Submitted','Under Review','Approved','Implemented','Rejected'] as $s) {
        if (in_array($role, $individualRoles, true)) {
            $q = $pdo->prepare("SELECT COUNT(*) FROM ideas WHERE submitter_id=? AND status=?");
            $q->execute([$uid, $s]);
        } else {
            $q = $pdo->prepare("SELECT COUNT(*) FROM ideas WHERE status=?");
            $q->execute([$s]);
        }
        $counts[$s] = (int)$q->fetchColumn();
    }

    // Pending + overdue reviews for reviewer roles
    $pendingReviews = 0; $overdueReviews = 0;
    if (in_array($role, array_merge($teamRoles, $adminRoles), true)) {
        if (in_array($role, $teamRoles, true)) {
            $prStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM ideas i JOIN users u ON u.id=i.submitter_id
                 WHERE i.status IN ('Submitted','Under Review')
                 AND (i.current_reviewer_id=? OR (i.current_reviewer_id IS NULL AND u.manager_id=?))"
            );
            $prStmt->execute([$uid, $uid]);
            $pendingReviews = (int)$prStmt->fetchColumn();

            $odStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM ideas i JOIN users u ON u.id=i.submitter_id
                 WHERE i.status IN ('Submitted','Under Review')
                 AND i.review_due_date IS NOT NULL AND i.review_due_date < CURDATE()
                 AND (i.current_reviewer_id=? OR (i.current_reviewer_id IS NULL AND u.manager_id=?))"
            );
            $odStmt->execute([$uid, $uid]);
            $overdueReviews = (int)$odStmt->fetchColumn();
        } else {
            $pendingReviews = (int)$pdo->query(
                "SELECT COUNT(*) FROM ideas WHERE status IN ('Submitted','Under Review')"
            )->fetchColumn();
            $overdueReviews = (int)$pdo->query(
                "SELECT COUNT(*) FROM ideas WHERE status IN ('Submitted','Under Review')
                 AND review_due_date IS NOT NULL AND review_due_date < CURDATE()"
            )->fetchColumn();
        }
    }

    // Recent activity (last 10 workflow entries)
    $recent = $pdo->query(
        "SELECT w.*, u.name AS actor_name, i.idea_code, i.title
         FROM idea_workflow w
         JOIN users u ON u.id = w.actor_id
         JOIN ideas i ON i.id = w.idea_id
         ORDER BY w.created_at DESC LIMIT 10"
    )->fetchAll();
    foreach ($recent as &$r) { $r['actor_name'] = esc($r['actor_name'] ?? ''); }
    unset($r);

    // Fetch current points from DB (authoritative)
    $ptsStmt = $pdo->prepare("SELECT points FROM users WHERE id=?");
    $ptsStmt->execute([$uid]);
    $userPoints = (int)($ptsStmt->fetchColumn() ?: $user['points']);

    respond([
        'success'         => true,
        'total'           => $total,
        'counts'          => $counts,
        'recent'          => $recent,
        'user_points'     => $userPoints,
        'pending_reviews' => $pendingReviews,
        'overdue_reviews' => $overdueReviews,
    ]);
}

// ── ASSIGN REVIEWERS (convert to multi-reviewer workflow) ─────────
if ($action === 'assign_reviewers' && $method === 'POST') {
    requireCsrf();
    requireRole('team_lead','project_lead','manager','senior_manager','admin','executive','super_admin');
    $b           = json_decode(file_get_contents('php://input'), true) ?? [];
    $ideaId      = (int)($b['idea_id']      ?? 0);
    $reviewerIds = array_map('intval', $b['reviewer_ids'] ?? []);
    $threshold   = max(1, min(100, (int)($b['threshold'] ?? 100)));

    if (!$ideaId || empty($reviewerIds)) {
        respond(['success'=>false,'error'=>'idea_id and reviewer_ids required.'], 400);
    }

    $pdo  = db();
    $stmt = $pdo->prepare("SELECT * FROM ideas WHERE id=?");
    $stmt->execute([$ideaId]);
    $idea = $stmt->fetch();
    if (!$idea) respond(['success'=>false,'error'=>'Idea not found.'], 404);

    // Submitter cannot be a reviewer
    $reviewerIds = array_values(array_unique(
        array_filter($reviewerIds, fn($rid) => $rid !== (int)$idea['submitter_id'])
    ));
    if (empty($reviewerIds)) {
        respond(['success'=>false,'error'=>'No valid reviewers — submitter cannot review own idea.'], 400);
    }

    // Remove existing reviewers (allows re-routing)
    $pdo->prepare("DELETE FROM idea_reviewers WHERE idea_id=?")->execute([$ideaId]);

    $pdo->prepare(
        "UPDATE ideas SET workflow_type='multi_reviewer', approval_threshold=?,
         status='Under Review', updated_at=NOW() WHERE id=?"
    )->execute([$threshold, $ideaId]);

    $ins = $pdo->prepare("INSERT INTO idea_reviewers (idea_id, reviewer_id) VALUES (?, ?)");
    foreach ($reviewerIds as $rid) {
        $ins->execute([$ideaId, $rid]);
        addNotification($rid, 'Review Assigned',
            "You have been assigned to review idea {$idea['idea_code']}: {$idea['title']}.",
            $ideaId);
    }

    addWorkflow($ideaId, $user['id'], 'Reviewed',
        'Routed to committee (' . count($reviewerIds) . ' reviewers, threshold: ' . $threshold . '%)');
    addNotification($idea['submitter_id'], 'Idea Under Committee Review',
        "Your idea {$idea['idea_code']} has been routed to a review committee.", $ideaId);

    respond(['success'=>true, 'reviewer_count'=>count($reviewerIds)]);
}

// ── REVIEWER INDIVIDUAL DECISION ─────────────────────────────────
if ($action === 'reviewer_decision' && $method === 'POST') {
    requireCsrf();
    requireRole('team_lead','project_lead','manager','senior_manager','admin','executive','super_admin');
    $b        = json_decode(file_get_contents('php://input'), true) ?? [];
    $ideaId   = (int)($b['idea_id']  ?? 0);
    $decision = strtolower($b['decision'] ?? '');
    $comment  = trim($b['comment'] ?? '');

    if (!$ideaId || !in_array($decision, ['approved','rejected'], true)) {
        respond(['success'=>false,'error'=>'Invalid idea_id or decision.'], 400);
    }

    $pdo = db();
    $revRow = $pdo->prepare("SELECT * FROM idea_reviewers WHERE idea_id=? AND reviewer_id=? LIMIT 1");
    $revRow->execute([$ideaId, $user['id']]);
    $rev = $revRow->fetch();
    if (!$rev) respond(['success'=>false,'error'=>'You are not an assigned reviewer for this idea.'], 403);
    if ($rev['decision'] !== 'pending') respond(['success'=>false,'error'=>'You have already submitted your decision.'], 409);

    $pdo->prepare("UPDATE idea_reviewers SET decision=?, comment=?, decided_at=NOW() WHERE idea_id=? AND reviewer_id=?")
        ->execute([$decision, $comment ?: null, $ideaId, $user['id']]);

    addWorkflow($ideaId, $user['id'], $decision === 'approved' ? 'Approved' : 'Rejected', $comment ?: null);

    $ideaStmt = $pdo->prepare("SELECT * FROM ideas WHERE id=?");
    $ideaStmt->execute([$ideaId]);
    $idea = $ideaStmt->fetch();

    $decStmt = $pdo->prepare("SELECT decision FROM idea_reviewers WHERE idea_id=?");
    $decStmt->execute([$ideaId]);
    $allDecisions = $decStmt->fetchAll(PDO::FETCH_COLUMN);

    $total    = count($allDecisions);
    $approved = count(array_filter($allDecisions, fn($d) => $d === 'approved'));
    $rejected = count(array_filter($allDecisions, fn($d) => $d === 'rejected'));
    $pending  = count(array_filter($allDecisions, fn($d) => $d === 'pending'));
    $cfg = getApprovalConfig();
    $threshold = $cfg['mode'] === 'custom'
        ? $cfg['threshold']
        : (int)($idea['approval_threshold'] ?? 100);

    $newStatus = null; $pts = 0;

    if ($threshold === 100 && $rejected > 0) {
        $newStatus = 'Rejected';
    } elseif ($pending === 0) {
        $rate = $total > 0 ? ($approved / $total) * 100 : 0;
        if ($rate >= $threshold) { $newStatus = 'Approved'; $pts = POINTS_APPROVED; }
        else { $newStatus = 'Rejected'; }
    }

    if ($newStatus) {
        $pdo->prepare("UPDATE ideas SET status=?, updated_at=NOW() WHERE id=?")->execute([$newStatus, $ideaId]);
        if ($pts > 0) {
            addPoints($idea['submitter_id'], $pts);
            $pdo->prepare("UPDATE ideas SET points_awarded = points_awarded + ? WHERE id=?")->execute([$pts, $ideaId]);
        }
        $ideaCode = $idea['idea_code'] ?? ('#' . $ideaId);
        $summary = "{$approved}/{$total} approved";
        $msg = $newStatus === 'Approved'
            ? "Your idea {$ideaCode} was Approved by committee ({$summary})." . ($pts > 0 ? " +{$pts} points awarded." : "")
            : "Your idea {$ideaCode} was Rejected by committee ({$summary}).";
        addNotification($idea['submitter_id'], "Idea {$newStatus}", $msg, $ideaId);
    }

    respond(['success'=>true,'new_status'=>$newStatus,'approved'=>$approved,'rejected'=>$rejected,'pending'=>$pending,'total'=>$total]);
}

// ── DUPLICATE DETECTION ───────────────────────────────────────────
if ($action === 'check_duplicate') {
    $title = trim($_GET['title'] ?? '');
    if (strlen($title) < 5) respond(['success' => true, 'duplicates' => []]);

    $words = array_values(array_filter(
        explode(' ', preg_replace('/\s+/', ' ', strtolower($title))),
        fn($w) => strlen($w) > 3
    ));
    if (empty($words)) respond(['success' => true, 'duplicates' => []]);

    $like = '%' . implode('%', array_slice($words, 0, 4)) . '%';
    $stmt = db()->prepare(
        "SELECT id, idea_code, title, status FROM ideas WHERE title LIKE ? AND status != 'Draft' LIMIT 5"
    );
    $stmt->execute([$like]);
    respond(['success' => true, 'duplicates' => $stmt->fetchAll()]);
}

// ── BULK REVIEW ────────────────────────────────────────────────────
if ($action === 'bulk_review' && $method === 'POST') {
    requireCsrf();
    requireRole('team_lead','project_lead','manager','senior_manager','admin','executive','super_admin');
    $b        = json_decode(file_get_contents('php://input'), true) ?? [];
    $ideaIds  = array_map('intval', $b['idea_ids'] ?? []);
    $decision = $b['decision'] ?? '';
    $comment  = trim($b['comment'] ?? '');

    if (empty($ideaIds) || !in_array($decision, ['Approved','Rejected'], true)) {
        respond(['success' => false, 'error' => 'idea_ids array and valid decision (Approved/Rejected) required.'], 400);
    }

    $pdo = db();
    $processed = 0;
    foreach ($ideaIds as $ideaId) {
        $stmt = $pdo->prepare("SELECT * FROM ideas WHERE id=? AND status IN ('Submitted','Under Review')");
        $stmt->execute([$ideaId]);
        $idea = $stmt->fetch();
        if (!$idea || (int)$idea['submitter_id'] === (int)$user['id']) continue;

        $pdo->prepare("UPDATE ideas SET status=?, updated_at=NOW() WHERE id=?")->execute([$decision, $ideaId]);
        addWorkflow($ideaId, $user['id'], $decision, $comment ?: null);

        $pts = $decision === 'Approved' ? POINTS_APPROVED : 0;
        if ($pts > 0) {
            addPoints($idea['submitter_id'], $pts);
            $pdo->prepare("UPDATE ideas SET points_awarded = points_awarded + ? WHERE id=?")->execute([$pts, $ideaId]);
        }

        $msg = $decision === 'Approved'
            ? "Your idea {$idea['idea_code']} was Approved (bulk). +{$pts} points awarded."
            : "Your idea {$idea['idea_code']} was Rejected (bulk)." . ($comment ? " Feedback: {$comment}" : "");
        addNotification($idea['submitter_id'], "Idea {$decision}", $msg, $ideaId);
        $processed++;
    }

    respond(['success' => true, 'processed' => $processed]);
}

// ── UPDATE ROI ─────────────────────────────────────────────────────
if ($action === 'update_roi' && $method === 'POST') {
    requireCsrf();
    requireRole('manager','senior_manager','executive','admin','super_admin');
    $b        = json_decode(file_get_contents('php://input'), true) ?? [];
    $ideaId   = (int)($b['idea_id'] ?? 0);
    $roiValue = (isset($b['roi_value']) && $b['roi_value'] !== '') ? (float)$b['roi_value'] : null;
    $roiType  = $b['roi_type'] ?? null;
    $roiDesc  = trim($b['roi_description'] ?? '') ?: null;

    $validTypes = ['cost_saving','time_saving','quality_improvement','revenue_increase','other'];
    if (!$ideaId) respond(['success' => false, 'error' => 'idea_id required.'], 400);
    if ($roiType && !in_array($roiType, $validTypes, true)) {
        respond(['success' => false, 'error' => 'Invalid roi_type.'], 400);
    }

    db()->prepare(
        "UPDATE ideas SET roi_value=?, roi_type=?, roi_description=?, updated_at=NOW() WHERE id=?"
    )->execute([$roiValue, $roiType ?: null, $roiDesc, $ideaId]);

    addWorkflow($ideaId, $user['id'], 'ROI Updated',
        ($roiType ? ucwords(str_replace('_', ' ', $roiType)) : '') .
        ($roiValue !== null ? ': ' . number_format($roiValue, 2) : ''));

    respond(['success' => true]);
}

// ── UPDATE IMPLEMENTATION TRACKING ────────────────────────────────
if ($action === 'update_implementation' && $method === 'POST') {
    requireCsrf();
    requireRole('manager','senior_manager','executive','admin','super_admin');
    $b          = json_decode(file_get_contents('php://input'), true) ?? [];
    $ideaId     = (int)($b['idea_id'] ?? 0);
    $ownerId    = !empty($b['implementation_owner_id']) ? (int)$b['implementation_owner_id'] : null;
    $targetDate = !empty($b['implementation_target_date']) ? $b['implementation_target_date'] : null;
    $implStatus = $b['implementation_status'] ?? null;

    $validStatuses = ['not_started','in_progress','completed','on_hold'];
    if (!$ideaId) respond(['success' => false, 'error' => 'idea_id required.'], 400);
    if ($implStatus && !in_array($implStatus, $validStatuses, true)) {
        respond(['success' => false, 'error' => 'Invalid implementation_status.'], 400);
    }

    db()->prepare(
        "UPDATE ideas SET implementation_owner_id=?, implementation_target_date=?,
         implementation_status=?, updated_at=NOW() WHERE id=?"
    )->execute([$ownerId, $targetDate, $implStatus ?: null, $ideaId]);

    addWorkflow($ideaId, $user['id'], 'Implementation Updated',
        $implStatus ? 'Status: ' . ucwords(str_replace('_', ' ', $implStatus)) : null);

    respond(['success' => true]);
}

// ── COMMUNITY VOTING BOARD ─────────────────────────────────────────
if ($action === 'board') {
    $uid  = (int)$user['id'];
    $sort = $_GET['sort'] ?? 'votes';

    $orderBy = match($sort) {
        'recent' => 'i.created_at DESC',
        'score'  => 'i.ai_score DESC',
        default  => 'upvotes DESC',
    };

    $stmt = db()->prepare(
        "SELECT i.id, i.idea_code, i.title, i.present_situation, i.proposed_solution,
                i.impact_level, i.status, i.created_at, i.is_anonymous, i.ai_score,
                u.name AS submitter_name, u.avatar_initials, u.department,
                (SELECT COUNT(*) FROM idea_community_votes WHERE idea_id=i.id AND vote_type='up')   AS upvotes,
                (SELECT COUNT(*) FROM idea_community_votes WHERE idea_id=i.id AND vote_type='down') AS downvotes,
                (SELECT vote_type FROM idea_community_votes WHERE idea_id=i.id AND user_id=?)  AS user_vote
         FROM ideas i
         JOIN users u ON u.id = i.submitter_id
         WHERE i.status IN ('Submitted','Under Review','Approved','Implemented')
         ORDER BY {$orderBy}, i.created_at DESC
         LIMIT 100"
    );
    $stmt->execute([$uid]);
    $ideas = $stmt->fetchAll();

    $canSeeAnon = in_array($user['role'], ['manager','senior_manager','executive','admin','super_admin'], true);
    foreach ($ideas as &$idea) {
        // Escape all user-provided name fields
        $idea['submitter_name'] = esc($idea['submitter_name'] ?? '');
        if (!empty($idea['is_anonymous']) && !$canSeeAnon) {
            $idea['submitter_name']  = 'Anonymous';
            $idea['avatar_initials'] = '?';
            $idea['department']      = '—';
        }
    }
    unset($idea);
    respond(['success' => true, 'ideas' => $ideas]);
}

// ── COMMUNITY VOTE (up / down toggle) ─────────────────────────────
if ($action === 'community_vote' && $method === 'POST') {
    requireCsrf();
    $b        = json_decode(file_get_contents('php://input'), true) ?? [];
    $ideaId   = (int)($b['idea_id'] ?? 0);
    $voteType = $b['vote_type'] ?? '';

    if (!$ideaId || !in_array($voteType, ['up','down'], true)) {
        respond(['success' => false, 'error' => 'idea_id and vote_type (up/down) required.'], 400);
    }

    $pdo = db();
    $ideaChk = $pdo->prepare("SELECT id, submitter_id, status FROM ideas WHERE id=? LIMIT 1");
    $ideaChk->execute([$ideaId]);
    $idea = $ideaChk->fetch();
    if (!$idea || !in_array($idea['status'], ['Submitted','Under Review','Approved','Implemented'], true)) {
        respond(['success' => false, 'error' => 'Idea not available for voting.'], 404);
    }
    if ((int)$idea['submitter_id'] === (int)$user['id']) {
        respond(['success' => false, 'error' => 'You cannot vote on your own idea.'], 403);
    }

    $existing = $pdo->prepare("SELECT vote_type FROM idea_community_votes WHERE idea_id=? AND user_id=? LIMIT 1");
    $existing->execute([$ideaId, $user['id']]);
    $current = $existing->fetchColumn();

    if ($current === $voteType) {
        $pdo->prepare("DELETE FROM idea_community_votes WHERE idea_id=? AND user_id=?")->execute([$ideaId, $user['id']]);
        $newVote = null;
    } elseif ($current) {
        $pdo->prepare("UPDATE idea_community_votes SET vote_type=? WHERE idea_id=? AND user_id=?")
            ->execute([$voteType, $ideaId, $user['id']]);
        $newVote = $voteType;
    } else {
        $pdo->prepare("INSERT INTO idea_community_votes (idea_id, user_id, vote_type) VALUES (?, ?, ?)")
            ->execute([$ideaId, $user['id'], $voteType]);
        $newVote = $voteType;
    }

    $upStmt = $pdo->prepare("SELECT COUNT(*) FROM idea_community_votes WHERE idea_id=? AND vote_type='up'");
    $upStmt->execute([$ideaId]);
    $upvotes   = (int)$upStmt->fetchColumn();
    $dnStmt    = $pdo->prepare("SELECT COUNT(*) FROM idea_community_votes WHERE idea_id=? AND vote_type='down'");
    $dnStmt->execute([$ideaId]);
    $downvotes = (int)$dnStmt->fetchColumn();

    respond(['success' => true, 'upvotes' => $upvotes, 'downvotes' => $downvotes, 'user_vote' => $newVote]);
}

respond(['success' => false, 'error' => 'Unknown action'], 400);
