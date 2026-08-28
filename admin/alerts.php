<?php
declare(strict_types=1);
// 14.7 — Predictive Risk & Alerts console.
require_once __DIR__ . '/../src/bootstrap.php';
$user = Auth::requireLogin();
$pdo = ops_db();
$alerts = new Alerts($pdo);

$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ops_require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'scan') {
        $stats = $alerts->scan();
        $notice = "Scan complete — {$stats['candidates']} conditions checked · {$stats['created']} new · {$stats['updated']} deduplicated · {$stats['auto_resolved']} auto-resolved.";
    } elseif ($action === 'ack') {
        $alerts->acknowledge((int) ($_POST['id'] ?? 0));
        $notice = 'Alert acknowledged.';
    } elseif ($action === 'resolve') {
        $alerts->resolve((int) ($_POST['id'] ?? 0));
        $notice = 'Alert resolved.';
    }
}

$filter = (string) ($_GET['type'] ?? '');
$open = $alerts->open();
$visible = $filter === '' ? $open : array_values(array_filter($open, fn ($a) => $a['type'] === $filter));
$counts = $alerts->countsBySeverity();
$events = $pdo->query('SELECT a.alert_key, ev.event, ev.details, ev.created_at FROM ops_alert_events ev JOIN ops_alerts a ON a.id = ev.alert_id ORDER BY ev.id DESC LIMIT 12')->fetchAll();
$types = array_unique(array_merge(Alerts::TYPES, array_column($open, 'type')));
$token = ops_csrf_token();

$body = '
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div><h1 class="h4 fw-bold mb-1">Risk &amp; Alerts <span class="text-secondary fs-6 fw-normal">· 14.7</span></h1>
  <div class="text-secondary small">' . $counts['critical'] . ' critical · ' . $counts['warning'] . ' warning · ' . $counts['info'] . ' info — prioritized P1–P4, deduplicated on re-detection</div></div>
  <form method="post"><input type="hidden" name="csrf" value="' . e($token) . '"><input type="hidden" name="action" value="scan">
    <button class="btn btn-primary fw-bold"><i class="bi bi-radar"></i> Run detection scan</button></form>
</div>'
. ($notice ? '<div class="alert alert-success py-2">' . e($notice) . '</div>' : '') . '
<div class="btn-group flex-wrap mb-3 gap-1">
  <a class="btn btn-sm ' . ($filter === '' ? 'btn-dark' : 'btn-light') . '" href="alerts.php">All (' . count($open) . ')</a>'
  . implode('', array_map(fn ($t) => '<a class="btn btn-sm ' . ($filter === $t ? 'btn-dark' : 'btn-light') . '" href="alerts.php?type=' . e($t) . '">' . e($t) . '</a>', $types)) . '
</div>
<div class="card mb-4"><div class="card-body">
<div class="table-responsive"><table class="table table-hover align-middle">
<thead><tr><th>Pri</th><th>Severity</th><th>Alert</th><th>Entity</th><th>Score</th><th>Triggers</th><th>Last seen</th><th></th></tr></thead><tbody>';
if (!$visible) {
    $body .= '<tr><td colspan="8" class="text-secondary text-center py-4">No open alerts' . ($filter ? ' of this type' : '') . '.</td></tr>';
}
foreach ($visible as $a) {
    $body .= '<tr>
      <td><span class="pill sev-' . e($a['severity']) . '">' . Alerts::priorityTier((int) $a['priority_score']) . '</span></td>
      <td>' . Ui::severityBadge((string) $a['severity']) . '</td>
      <td class="small"><div class="fw-bold">' . e($a['title']) . '</div><div class="text-secondary" style="font-size:.76rem">' . e($a['message']) . '</div></td>
      <td><code class="small">' . e((string) $a['entity_type']) . '#' . (int) $a['entity_id'] . '</code><div>' . Ui::statusBadge((string) $a['status']) . '</div></td>
      <td class="fw-bold">' . (int) $a['priority_score'] . '</td>
      <td>' . (int) $a['trigger_count'] . '×</td>
      <td class="small text-secondary">' . e((string) $a['last_triggered_at']) . '</td>
      <td class="text-end">
        <form method="post" class="d-inline"><input type="hidden" name="csrf" value="' . e($token) . '"><input type="hidden" name="action" value="ack"><input type="hidden" name="id" value="' . (int) $a['id'] . '"><button class="btn btn-sm btn-light" ' . ($a['status'] !== 'new' ? 'disabled' : '') . '>Ack</button></form>
        <form method="post" class="d-inline"><input type="hidden" name="csrf" value="' . e($token) . '"><input type="hidden" name="action" value="resolve"><input type="hidden" name="id" value="' . (int) $a['id'] . '"><button class="btn btn-sm btn-outline-danger">Resolve</button></form>
      </td></tr>';
}
$body .= '</tbody></table></div></div></div>

<div class="card"><div class="card-body">
<h2 class="h6 fw-bold">Automation / alert event log <span class="text-secondary fw-normal small">(14.7.10)</span></h2>
<div class="table-responsive"><table class="table table-sm small"><thead><tr><th>When</th><th>Alert</th><th>Event</th><th>Details</th></tr></thead><tbody>';
foreach ($events as $ev) {
    $body .= '<tr><td class="text-secondary">' . e($ev['created_at']) . '</td><td><code>' . e($ev['alert_key']) . '</code></td><td>' . Ui::statusBadge((string) $ev['event']) . '</td><td class="text-secondary">' . e((string) $ev['details']) . '</td></tr>';
}
$body .= '</tbody></table></div></div></div>';
echo Ui::layout('Risk & Alerts', 'alerts', $body, $user);
