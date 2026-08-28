<?php
declare(strict_types=1);
// 14.9 — Automation engine: rules, manual runs, history, monitoring.
require_once __DIR__ . '/../src/bootstrap.php';
$user = Auth::requireLogin();
$pdo = ops_db();
$engine = new Automation($pdo);

$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ops_require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $rule = $id ? $engine->rule($id) : null;
    if ($rule) {
        if ($action === 'toggle') {
            $engine->set_active($id, (int) $rule['is_active'] !== 1);
            $notice = 'Rule ' . ((int) $rule['is_active'] === 1 ? 'disabled' : 'enabled') . '.';
        } elseif ($action === 'run') {
            $status = $engine->run($rule, 'manual');
            $notice = 'Manual run finished with status: ' . $status . ($status === 'duplicate_blocked' ? ' (identical execution inside the dedup window — prevented)' : '') . '.';
        }
    } elseif ($action === 'run_due') {
        $ran = $engine->runDueRules();
        $notice = $ran ? 'Processed ' . count($ran) . ' due rule(s): ' . implode(', ', array_map(fn ($r) => $r['name'] . ' → ' . $r['status'], $ran)) . '.' : 'No rules due right now.';
    }
}

$monitoring = $engine->monitoring();
$runs = $engine->runs(40);
$token = ops_csrf_token();

$body = '
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div><h1 class="h4 fw-bold mb-1">Automation Engine <span class="text-secondary fs-6 fw-normal">· 14.9</span></h1>
  <div class="text-secondary small">Rules evaluated against triggers · duplicate prevention · full execution history &amp; failure logging</div></div>
  <form method="post"><input type="hidden" name="csrf" value="' . e($token) . '"><input type="hidden" name="action" value="run_due">
    <button class="btn btn-primary fw-bold"><i class="bi bi-play-fill"></i> Process due scheduled rules</button></form>
</div>'
. ($notice ? '<div class="alert alert-success py-2">' . e($notice) . '</div>' : '') . '
<div class="card mb-4"><div class="card-body">
<h2 class="h6 fw-bold">Rules <span class="text-secondary fw-normal small">(14.9.1)</span></h2>
<div class="table-responsive"><table class="table table-hover align-middle">
<thead><tr><th>Rule</th><th>Trigger</th><th>Conditions</th><th>Action</th><th>Schedule</th><th>Dedup</th><th>Runs (ok/fail)</th><th>Success rate</th><th>Last run</th><th></th></tr></thead><tbody>';
foreach ($monitoring as $m) {
    $r = $m['rule'];
    $rate = $m['success_rate_pct'] === null ? '—' : ops_pct((float) $m['success_rate_pct']);
    $rateTone = $m['success_rate_pct'] === null || $m['success_rate_pct'] >= 90 ? 'text-success' : ($m['success_rate_pct'] >= 60 ? 'text-warning' : 'text-danger');
    $body .= '<tr>
      <td class="small fw-bold">' . e($r['name']) . '<div>' . Ui::statusBadge((int) $r['is_active'] === 1 ? 'active' : 'inactive') . '</div></td>
      <td><code class="small">' . e($r['trigger_type']) . '</code></td>
      <td class="small text-secondary"><code style="font-size:.72rem">' . e((string) $r['conditions_json'] ?? 'always') . '</code></td>
      <td><code class="small">' . e($r['action_type']) . '</code><div class="small text-secondary" style="font-size:.72rem">' . e((string) $r['action_config_json'] ?? '') . '</div></td>
      <td class="small">' . e($r['schedule']) . '</td>
      <td class="small">' . (int) $r['dedup_minutes'] . 'm</td>
      <td class="small">' . (int) $r['run_count'] . ' <span class="text-secondary">/</span> <span class="text-danger">' . (int) $r['fail_count'] . '</span></td>
      <td class="small fw-bold ' . $rateTone . '">' . $rate . '</td>
      <td class="small text-secondary">' . e((string) $r['last_run_at'] ?? 'never') . '</td>
      <td class="text-end">
        <form method="post" class="d-inline"><input type="hidden" name="csrf" value="' . e($token) . '"><input type="hidden" name="action" value="run"><input type="hidden" name="id" value="' . (int) $r['id'] . '"><button class="btn btn-sm btn-light">Run now</button></form>
        <form method="post" class="d-inline"><input type="hidden" name="csrf" value="' . e($token) . '"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . (int) $r['id'] . '"><button class="btn btn-sm btn-outline-secondary">' . ((int) $r['is_active'] === 1 ? 'Disable' : 'Enable') . '</button></form>
      </td></tr>';
}
$body .= '</tbody></table></div></div></div>

<div class="card"><div class="card-body">
<h2 class="h6 fw-bold">Execution history <span class="text-secondary fw-normal small">(14.9.6 failure logging · 14.9.9 monitoring)</span></h2>
<div class="table-responsive"><table class="table table-sm small align-middle">
<thead><tr><th>#</th><th>Rule</th><th>Trigger</th><th>Status</th><th>Started</th><th>Outcome</th></tr></thead><tbody>';
if (!$runs) {
    $body .= '<tr><td colspan="6" class="text-center text-secondary py-4">No executions yet — run a rule or process the schedule.</td></tr>';
}
foreach ($runs as $run) {
    $outcome = json_decode((string) $run['outcome_json'], true) ?: [];
    $outcomeText = $run['error_message'] ? '<span class="text-danger">' . e((string) $run['error_message']) . '</span>' : e(json_encode($outcome, JSON_UNESCAPED_SLASHES));
    $body .= '<tr><td>' . (int) $run['id'] . '</td><td class="fw-bold">' . e((string) ($run['rule_name'] ?? '#' . $run['rule_id'])) . '</td>
      <td><code>' . e($run['trigger_type']) . '</code></td><td>' . Ui::statusBadge((string) $run['status']) . '</td>
      <td class="text-secondary">' . e($run['started_at']) . '</td><td style="max-width:420px;overflow-wrap:anywhere" class="text-secondary">' . $outcomeText . '</td></tr>';
}
$body .= '</tbody></table></div></div></div>';
echo Ui::layout('Automation Engine', 'automation', $body, $user);
