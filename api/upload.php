<?php
require_once __DIR__ . '/config.php';

$user   = requireAuth();
$action = $_GET['action'] ?? 'upload';

if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $ideaId  = (int)($_POST['idea_id'] ?? 0);
    $section = $_POST['section'] ?? 'situation';

    if (!$ideaId || !in_array($section, ['situation', 'solution'], true)) {
        respond(['success' => false, 'error' => 'Invalid parameters.'], 400);
    }

    $stmt = db()->prepare("SELECT id FROM ideas WHERE id=? AND submitter_id=?");
    $stmt->execute([$ideaId, $user['id']]);
    if (!$stmt->fetch()) {
        respond(['success' => false, 'error' => 'Unauthorized or idea not found.'], 403);
    }

    if (empty($_FILES['file'])) {
        respond(['success' => false, 'error' => 'No file uploaded.'], 400);
    }

    $file     = $_FILES['file'];
    $maxBytes = MAX_FILE_MB * 1024 * 1024;

    if ($file['size'] > $maxBytes) {
        respond(['success' => false, 'error' => 'File exceeds ' . MAX_FILE_MB . 'MB limit.'], 400);
    }

    $allowed = ['pdf','png','jpg','jpeg','gif','xlsx','xls','csv','docx','doc'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        respond(['success' => false, 'error' => 'File type not allowed.'], 400);
    }

    $dir = uploadDir();

    $safeName = uniqid('attach_', true) . '.' . $ext;
    $destPath = $dir . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        respond(['success' => false, 'error' => 'Failed to save file.'], 500);
    }

    db()->prepare(
        "INSERT INTO idea_attachments (idea_id,section,filename,filepath) VALUES (?,?,?,?)"
    )->execute([$ideaId, $section, $file['name'], $safeName]);

    respond(['success' => true, 'filename' => $file['name'], 'url' => uploadUrl() . $safeName]);
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $b   = json_decode(file_get_contents('php://input'), true) ?? [];
    $aid = (int)($b['attachment_id'] ?? 0);

    $stmt = db()->prepare(
        "SELECT a.* FROM idea_attachments a
         JOIN ideas i ON i.id = a.idea_id
         WHERE a.id=? AND i.submitter_id=?"
    );
    $stmt->execute([$aid, $user['id']]);
    $att = $stmt->fetch();
    if (!$att) respond(['success' => false, 'error' => 'Not found or unauthorized.'], 403);

    @unlink(uploadDir() . $att['filepath']);
    db()->prepare("DELETE FROM idea_attachments WHERE id=?")->execute([$aid]);
    respond(['success' => true]);
}

respond(['success' => false, 'error' => 'Unknown action'], 400);
