<?php
declare(strict_types=1);
// 14.8 — Automated reports: generate, browse historical snapshots, view payload.
require_once __DIR__ . '/../src/bootstrap.php';
$user = Auth::requireLogin();
$pdo = ops_db();
$reports = new Reports($pdo);

$notice = $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ops_require_csrf();
    $type = (string) ($_POST['type'] ?? '');
    try {
        $payload = $reports->generate($type, 'admin:' . $user['email']);
        $notice = 'Generated ' . (Reports::TYPES[$type] ?? $type) . ' — snapshot #' . $payload['meta']['snapshot_id']
            . ($payload['meta']['validation']['ok'] ? ' (validation passed)' : ' (validation FAILED — stored as draft)');
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

$view = isset($_GET['view']) ? $reports->snapshot((int) $_GET['view']) : null;
$filter = (string) ($_GET['type'] ?? '');
$snaps = $reports->snapshots($filter);
$token = ops_csrf_token();

$body = '
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div><h1 class="h4 fw-bold mb-1">Automated Reports <span class="text-secondary fs-6 fw-normal">· 14.8</span></h1>
  <div class="text-secondary small">8 report types · every generation stored as a historical snapshot · payload validation before finalising</div></div>
</div>'
. ($notice ? '<div class="alert alert-success py-2">' . e($notice) . '</div>' : '')
. ($error ? '<div class="alert alert-danger py-2">' . e($error) . '</div>' : '') . '
<div class="row g-2 mb-4">'
. implode('', array_map(fn ($t, $label) => '<div class="col-6 col-md-3"><form method="post"><input type="hidden" name="csrf" value="' . e($token) . '"><input type="hidden" name="type" value="' . e($t) . '"><button class="btn btn-light w-100 border py-3 fw-bold"><i class="bi bi-file-earmark-arrow-down"></i> ' . e($label) . '</button></form></div>', array_keys(Reports::TYPES), Reports::TYPES)) . '
</div>';

if ($view) {
    $payload = json_decode((string) $view['payload_json'], true) ?: [];
    $body .= '<div class="card mb-4"><div class="card-body">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <div><h2 class="h6 fw-bold mb-1">' . e(Reports::TYPES[$view['report_type']] ?? $view['report_type']) . ' — snapshot #' . (int) $view['id'] . '</h2>
        <div class="small text-secondary">' . e($view['period_label']) . ' (' . e($view['period_start']) . ' → ' . e($view['period_end']) . ') · generated ' . e($view['generated_at']) . ' by ' . e($view['generated_by']) . ' · rows: ' . (int) $view['row_count'] . '</div></div>
        <div>' . Ui::statusBadge((string) $view['status']) . ' ' . ((int) $view['validation_ok'] ? '<span class="badge text-bg-success">validated</span>' : '<span class="badge text-bg-danger">validation failed</span>') . '</div>
      </div>'
      . ((int) $view['validation_ok'] === 0 ? '<div class="alert alert-danger py-2 small">' . e((string) $view['validation_errors']) . '</div>' : '');
    foreach (($payload['metrics'] ?? []) as $name => $value) {
        if (is_array($value)) {
            $body .= '<div class="border-bottom py-2"><span class="kpi-label">' . e((string) $name) . '</span><div class="small">' . e(json_encode($value, JSON_UNESCAPED_SLASHES)) . '</div></div>';
        } else {
            $pretty = is_numeric($value) && str_contains($name, 'value') || str_contains($name, 'total') || str_contains($name, 'profit') || str_contains($name, 'cost') || str_contains($name, 'outstanding') || str_contains($name, 'pipeline') ? ops_money((float) $value) : (string) $value;
            $body .= '<div class="d-flex justify-content-between border-bottom py-2"><span class="kpi-label">' . e((string) $name) . '</span><strong>' . e($pretty) . '</strong></div>';
        }
    }
    foreach (($payload['tables'] ?? []) as $tName => $tRows) {
        $fmtCell = function ($col, $val): string {
            if (!is_scalar($val)) {
                return e(json_encode($val, JSON_UNESCAPED_SLASHES));
            }
            $col = (string) $col;
            if (str_ends_with($col, '_pct') || $col === 'margin_pct' || $col === 'win_rate_pct') {
                return e(ops_pct((float) $val));
            }
            $moneyCols = ['budget', 'cost', 'invoiced', 'collected', 'overdue', 'revenue', 'gross_profit', 'labor_cost', 'total', 'v', 'value', 'amount'];
            if (in_array($col, $moneyCols, true) && is_numeric($val)) {
                return e(ops_money((float) $val));
            }
            if (is_array($val)) {
                return e(implode(', ', array_map('strval', $val)));
            }
            return e((string) $val);
        };
        $body .= '<h3 class="small fw-bold mt-3 text-secondary">' . e((string) $tName) . ' (' . count($tRows) . ')</h3><div class="table-responsive"><table class="table table-sm small"><thead><tr>';
        $cols = $tRows ? array_keys($tRows[0]) : [];
        $body .= implode('', array_map(fn ($c) => '<th>' . e((string) $c) . '</th>', $cols)) . '</tr></thead><tbody>';
        foreach ($tRows as $r) {
            $body .= '<tr>' . implode('', array_map(fn ($c) => '<td>' . $fmtCell($c, $r[$c] ?? '') . '</td>', $cols)) . '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    $body .= '</div></div>';
}

$body .= '<div class="card"><div class="card-body">
<h2 class="h6 fw-bold">Historical snapshots <span class="text-secondary fw-normal small">(14.8.9)</span></h2>
<div class="btn-group flex-wrap gap-1 mb-3"><a class="btn btn-sm ' . ($filter === '' ? 'btn-dark' : 'btn-light') . '" href="reports.php">All</a>'
. implode('', array_map(fn ($t) => '<a class="btn btn-sm ' . ($filter === $t ? 'btn-dark' : 'btn-light') . '" href="reports.php?type=' . e($t) . '">' . e(Reports::TYPES[$t]) . '</a>', array_keys(Reports::TYPES))) . '</div>
<div class="table-responsive"><table class="table table-hover align-middle small">
<thead><tr><th>#</th><th>Report</th><th>Period</th><th>Generated</th><th>By</th><th>Status</th><th></th></tr></thead><tbody>';
if (!$snaps) {
    $body .= '<tr><td colspan="7" class="text-center text-secondary py-4">No snapshots yet — generate one above or let the automation engine schedule it.</td></tr>';
}
foreach ($snaps as $s) {
    $body .= '<tr><td>' . (int) $s['id'] . '</td><td class="fw-bold">' . e(Reports::TYPES[$s['report_type']] ?? $s['report_type']) . '</td>
      <td>' . e($s['period_label']) . '</td><td class="text-secondary">' . e($s['generated_at']) . '</td><td>' . e($s['generated_by']) . '</td>
      <td>' . Ui::statusBadge((string) $s['status']) . ' ' . ((int) $s['validation_ok'] ? '' : '<span class="badge text-bg-danger">invalid</span>') . '</td>
      <td class="text-end"><a class="btn btn-sm btn-light" href="reports.php?view=' . (int) $s['id'] . ($filter ? '&type=' . e($filter) : '') . '">View</a></td></tr>';
}
$body .= '</tbody></table></div></div></div>';
echo Ui::layout('Automated Reports', 'reports', $body, $user);
