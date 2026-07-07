<?php
// api/export.php  –  Data export endpoints
// GET ?action=ideas&format=csv        — ideas list as CSV
// GET ?action=leaderboard&format=csv  — leaderboard as CSV
// GET ?action=analytics               — analytics as printable HTML
require_once __DIR__ . '/config.php';

$user   = requireAuth();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$pdo    = db();

// ── Helper: role-based visibility where-clause (mirrors ideas.php list) ────
function buildVisibilityClause(array $user, array &$params): string {
    $individualRoles = ['trainee', 'employee'];
    $teamRoles       = ['team_lead', 'project_lead', 'manager', 'senior_manager'];

    if (in_array($user['role'], $individualRoles, true)) {
        $params[] = $user['id'];
        $params[] = $user['id'];
        $params[] = $user['id'];
        return '(i.submitter_id = ? OR i.co_suggester_1_id = ? OR i.co_suggester_2_id = ?)';
    }

    if (in_array($user['role'], $teamRoles, true)) {
        $params[] = $user['id'];
        $params[] = $user['id'];
        return '(i.submitter_id IN (SELECT id FROM users WHERE manager_id = ?) OR i.submitter_id = ?)';
    }

    // exec / admin / super_admin — see all non-draft
    return "i.status != 'Draft'";
}

// ── EXPORT IDEAS CSV ───────────────────────────────────────────────
if ($action === 'ideas') {
    $params = [];
    $where  = [];

    $visibility = buildVisibilityClause($user, $params);
    $where[]    = '(' . $visibility . ')';

    if (!empty($_GET['status'])) {
        $where[]  = 'i.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['search'])) {
        $s        = '%' . $_GET['search'] . '%';
        $where[]  = '(i.title LIKE ? OR i.idea_code LIKE ?)';
        $params[] = $s;
        $params[] = $s;
    }
    if (!empty($_GET['impact'])) {
        $where[]  = 'i.impact_level = ?';
        $params[] = $_GET['impact'];
    }

    $sql = "SELECT i.idea_code, i.title, i.status,
                   u.name AS submitter_name, u.department,
                   i.impact_level, i.impact_areas, i.ai_score, i.submitted_at
            FROM ideas i
            JOIN users u ON u.id = i.submitter_id"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY i.submitted_at DESC LIMIT 10000";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ideas = $stmt->fetchAll();

    $filename = 'ideas_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');

    // UTF-8 BOM so Excel opens correctly
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Idea Code', 'Title', 'Status', 'Submitter', 'Department',
                   'Impact Level', 'Impact Areas', 'AI Score', 'Submitted At']);

    foreach ($ideas as $row) {
        fputcsv($out, [
            $row['idea_code'],
            $row['title'],
            $row['status'],
            $row['submitter_name'],
            $row['department'],
            $row['impact_level'],
            $row['impact_areas'],
            $row['ai_score'],
            $row['submitted_at'],
        ]);
    }

    fclose($out);
    exit;
}

// ── EXPORT LEADERBOARD CSV ─────────────────────────────────────────
if ($action === 'leaderboard') {
    $stmt = $pdo->query(
        "SELECT u.name, u.department,
                u.points,
                COUNT(DISTINCT i.id) AS idea_count,
                SUM(CASE WHEN i.status = 'Implemented' THEN 1 ELSE 0 END) AS implemented_count,
                ROUND(AVG(CASE WHEN i.status != 'Draft' THEN i.ai_score ELSE NULL END), 1) AS avg_score
         FROM users u
         LEFT JOIN ideas i ON i.submitter_id = u.id
         WHERE u.role NOT IN ('admin', 'super_admin')
         GROUP BY u.id
         ORDER BY u.points DESC"
    );
    $rows = $stmt->fetchAll();

    $filename = 'leaderboard_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Rank', 'Name', 'Department', 'Points', 'Ideas Submitted',
                   'Ideas Implemented', 'Avg AI Score']);

    $rank = 1;
    foreach ($rows as $row) {
        fputcsv($out, [
            $rank++,
            $row['name'],
            $row['department'],
            $row['points'],
            $row['idea_count'],
            $row['implemented_count'],
            $row['avg_score'] ?? 'N/A',
        ]);
    }

    fclose($out);
    exit;
}

// ── EXPORT ANALYTICS HTML ──────────────────────────────────────────
if ($action === 'analytics') {
    requireRole('admin', 'executive', 'manager', 'senior_manager', 'super_admin');

    // Fetch analytics data (same queries as users.php ?action=analytics)
    $trend = $pdo->query(
        "SELECT DATE_FORMAT(submitted_at, '%Y-%m') AS month,
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'Implemented' THEN 1 ELSE 0 END) AS implemented,
                ROUND(AVG(ai_score), 1) AS avg_score
         FROM ideas
         WHERE submitted_at IS NOT NULL
         GROUP BY month
         ORDER BY month ASC
         LIMIT 12"
    )->fetchAll();

    $statusSummary = $pdo->query(
        "SELECT status, COUNT(*) AS cnt FROM ideas GROUP BY status ORDER BY cnt DESC"
    )->fetchAll();

    $scoreStats = $pdo->query(
        "SELECT
            COALESCE(SUM(CASE WHEN ai_score >= 75 THEN 1 ELSE 0 END), 0) AS high_quality,
            COALESCE(SUM(CASE WHEN ai_score >= 50 AND ai_score < 75 THEN 1 ELSE 0 END), 0) AS medium_quality,
            COALESCE(SUM(CASE WHEN ai_score > 0 AND ai_score < 50 THEN 1 ELSE 0 END), 0) AS low_quality,
            ROUND(AVG(CASE WHEN status != 'Draft' THEN ai_score ELSE NULL END), 1) AS overall_avg,
            COUNT(CASE WHEN status != 'Draft' THEN 1 END) AS total_scored
         FROM ideas
         WHERE status != 'Draft'"
    )->fetch();

    $topDepts = $pdo->query(
        "SELECT u.department,
                COUNT(DISTINCT i.id) AS idea_count,
                SUM(CASE WHEN i.status = 'Implemented' THEN 1 ELSE 0 END) AS implemented,
                ROUND(AVG(CASE WHEN i.status != 'Draft' THEN i.ai_score ELSE NULL END), 1) AS avg_score
         FROM users u
         JOIN ideas i ON i.submitter_id = u.id
         WHERE i.status != 'Draft' AND u.department IS NOT NULL AND u.department != ''
         GROUP BY u.department
         ORDER BY idea_count DESC
         LIMIT 10"
    )->fetchAll();

    $generatedAt = date('F j, Y \a\t H:i');
    $orgName     = 'IFQM Ideation Tool';

    // Build HTML
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics Report – <?= htmlspecialchars($orgName) ?></title>
<style>
  /* ── Base ─── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 13px;
    color: #1e293b;
    background: #fff;
    padding: 32px 40px;
    line-height: 1.5;
  }
  h1 { font-size: 22px; font-weight: 700; color: #4f46e5; margin-bottom: 4px; }
  h2 { font-size: 15px; font-weight: 600; color: #1e293b; margin: 24px 0 10px; padding-bottom: 4px;
       border-bottom: 2px solid #e2e8f0; }
  .meta { font-size: 11px; color: #64748b; margin-bottom: 32px; }

  /* ── Stat cards ─── */
  .cards { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 8px; }
  .card {
    flex: 1 1 140px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 18px;
    background: #f8fafc;
  }
  .card .label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
  .card .value { font-size: 28px; font-weight: 700; color: #4f46e5; margin-top: 2px; }
  .card .sub   { font-size: 11px; color: #94a3b8; margin-top: 2px; }

  /* ── Tables ─── */
  table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
  th { background: #4f46e5; color: #fff; padding: 7px 10px; text-align: left; font-size: 12px; }
  td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
  tr:nth-child(even) td { background: #f8fafc; }
  tr:last-child td { border-bottom: none; }

  /* ── Trend bar chart ─── */
  .bar-chart { width: 100%; }
  .bar-row { display: flex; align-items: center; margin-bottom: 5px; gap: 8px; }
  .bar-label { width: 80px; font-size: 11px; color: #64748b; flex-shrink: 0; }
  .bar-track { flex: 1; background: #e2e8f0; border-radius: 4px; height: 14px; overflow: hidden; }
  .bar-fill  { height: 100%; background: #4f46e5; border-radius: 4px; }
  .bar-fill.impl { background: #10b981; }
  .bar-count { font-size: 11px; color: #64748b; width: 28px; text-align: right; flex-shrink: 0; }

  .legend { display: flex; gap: 16px; margin-bottom: 10px; }
  .legend-item { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #64748b; }
  .legend-dot { width: 12px; height: 12px; border-radius: 2px; }
  .dot-blue  { background: #4f46e5; }
  .dot-green { background: #10b981; }

  footer { margin-top: 40px; font-size: 10px; color: #94a3b8; text-align: center;
           border-top: 1px solid #e2e8f0; padding-top: 12px; }

  /* ── Print styles ─── */
  @media print {
    body { padding: 16px 20px; }
    h2   { page-break-before: auto; }
    .cards { page-break-inside: avoid; }
    table { page-break-inside: avoid; }
    .no-print { display: none !important; }
    @page { margin: 1.5cm; }
  }
</style>
</head>
<body>

<h1><?= htmlspecialchars($orgName) ?> – Analytics Report</h1>
<p class="meta">Generated on <?= htmlspecialchars($generatedAt) ?></p>

<?php
// ── Score quality cards ─────────────────────────────────────────
$sq    = $scoreStats;
$total = (int)($sq['total_scored'] ?? 0);
$high  = (int)($sq['high_quality'] ?? 0);
$med   = (int)($sq['medium_quality'] ?? 0);
$low   = (int)($sq['low_quality'] ?? 0);
$avg   = $sq['overall_avg'] ?? 'N/A';
?>

<h2>Idea Quality Overview</h2>
<div class="cards">
  <div class="card">
    <div class="label">Total Ideas Scored</div>
    <div class="value"><?= $total ?></div>
    <div class="sub">non-draft ideas</div>
  </div>
  <div class="card">
    <div class="label">Average AI Score</div>
    <div class="value"><?= $avg ?></div>
    <div class="sub">out of 100</div>
  </div>
  <div class="card">
    <div class="label">High Quality</div>
    <div class="value" style="color:#10b981"><?= $high ?></div>
    <div class="sub">score &ge; 75</div>
  </div>
  <div class="card">
    <div class="label">Medium Quality</div>
    <div class="value" style="color:#f59e0b"><?= $med ?></div>
    <div class="sub">50 – 74</div>
  </div>
  <div class="card">
    <div class="label">Low Quality</div>
    <div class="value" style="color:#ef4444"><?= $low ?></div>
    <div class="sub">score &lt; 50</div>
  </div>
</div>

<?php
// ── Status summary ──────────────────────────────────────────────
?>
<h2>Ideas by Status</h2>
<table>
  <thead><tr><th>Status</th><th>Count</th><th>% of Total</th></tr></thead>
  <tbody>
<?php
$grandTotal = array_sum(array_column($statusSummary, 'cnt')) ?: 1;
foreach ($statusSummary as $s):
    $pct = round(($s['cnt'] / $grandTotal) * 100, 1);
?>
    <tr>
      <td><?= htmlspecialchars($s['status']) ?></td>
      <td><?= (int)$s['cnt'] ?></td>
      <td><?= $pct ?>%</td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>

<?php
// ── Monthly trend ───────────────────────────────────────────────
if ($trend):
    $maxTotal = max(array_column($trend, 'total')) ?: 1;
?>
<h2>Monthly Submission Trend</h2>
<div class="legend">
  <div class="legend-item"><div class="legend-dot dot-blue"></div> Submitted</div>
  <div class="legend-item"><div class="legend-dot dot-green"></div> Implemented</div>
</div>
<div class="bar-chart">
<?php foreach ($trend as $t):
    $wSub  = round(($t['total'] / $maxTotal) * 100);
    $wImpl = $t['total'] > 0 ? round(($t['implemented'] / $t['total']) * $wSub) : 0;
?>
  <div class="bar-row">
    <div class="bar-label"><?= htmlspecialchars($t['month']) ?></div>
    <div class="bar-track">
      <div class="bar-fill" style="width:<?= $wSub ?>%"></div>
    </div>
    <div class="bar-count"><?= (int)$t['total'] ?></div>
    <div class="bar-track">
      <div class="bar-fill impl" style="width:<?= $wImpl ?>%"></div>
    </div>
    <div class="bar-count"><?= (int)$t['implemented'] ?></div>
  </div>
<?php endforeach; ?>
</div>

<table style="margin-top:12px">
  <thead><tr><th>Month</th><th>Submitted</th><th>Implemented</th><th>Avg AI Score</th></tr></thead>
  <tbody>
<?php foreach ($trend as $t): ?>
    <tr>
      <td><?= htmlspecialchars($t['month']) ?></td>
      <td><?= (int)$t['total'] ?></td>
      <td><?= (int)$t['implemented'] ?></td>
      <td><?= $t['avg_score'] ?? 'N/A' ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php
// ── Department breakdown ────────────────────────────────────────
if ($topDepts):
?>
<h2>Top Departments by Ideas</h2>
<table>
  <thead><tr><th>Department</th><th>Ideas</th><th>Implemented</th><th>Avg AI Score</th></tr></thead>
  <tbody>
<?php foreach ($topDepts as $d): ?>
    <tr>
      <td><?= htmlspecialchars($d['department']) ?></td>
      <td><?= (int)$d['idea_count'] ?></td>
      <td><?= (int)$d['implemented'] ?></td>
      <td><?= $d['avg_score'] ?? 'N/A' ?></td>
    </tr>
<?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<footer>
  This report was automatically generated by the IFQM Ideation Tool.
  &copy; <?= date('Y') ?> IFQM. All rights reserved.
</footer>

</body>
</html>
<?php
    $html = ob_get_clean();
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, no-store');
    echo $html;
    exit;
}

respond(['success' => false, 'error' => 'Unknown action'], 400);
