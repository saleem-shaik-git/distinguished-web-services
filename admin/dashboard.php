<?php
declare(strict_types=1);
// 14.10.1 — Executive KPI dashboard (+ 14.10.9 reports integration).
require_once __DIR__ . '/../src/bootstrap.php';
$user = Auth::requireLogin();
$pdo = ops_db();
$an = new Analytics($pdo);
$k = $an->execKpis();
$reports = $an->reportsIntegration();
$alertsView = $an->alertsView();

$severityTotal = $k['alerts']['critical'] + $k['alerts']['warning'] + $k['alerts']['info'];
$body = '
<h1 class="h4 fw-bold mb-4">Executive Dashboard <span class="text-secondary fs-6 fw-normal">· ' . date('D j M Y, g:ia') . '</span></h1>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">' . Ui::kpiCard('Revenue MTD (invoiced)', ops_money($k['revenue_mtd_invoiced']), 'collected ' . ops_money($k['revenue_mtd_collected']), 'blue') . '</div>
  <div class="col-6 col-lg-3">' . Ui::kpiCard('Outstanding AR', ops_money($k['outstanding_total']), $k['overdue_invoices'] . ' overdue invoice(s)', $k['overdue_invoices'] ? 'red' : 'dark') . '</div>
  <div class="col-6 col-lg-3">' . Ui::kpiCard('Company Margin', ops_pct($k['profitability']['company_margin_pct']), 'gross profit ' . ops_money($k['profitability']['gross_profit']), $k['profitability']['company_margin_pct'] >= OPS_LOW_MARGIN_THRESHOLD ? 'green' : 'amber') . '</div>
  <div class="col-6 col-lg-3">' . Ui::kpiCard('Pipeline Value', ops_money($k['pipeline_value']), $k['active_leads'] . ' active lead(s)') . '</div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">' . Ui::kpiCard('Active Projects', (string) $k['active_projects'], $k['overdue_projects'] . ' overdue', $k['overdue_projects'] ? 'amber' : 'green') . '</div>
  <div class="col-6 col-lg-3">' . Ui::kpiCard('Open Alerts', (string) $severityTotal, $k['alerts']['critical'] . ' critical / ' . $k['alerts']['warning'] . ' warning', $k['alerts']['critical'] ? 'red' : 'dark') . '</div>
  <div class="col-6 col-lg-3">' . Ui::kpiCard('Overdue Tasks', (string) $k['overdue_tasks'], 'across active projects') . '</div>
  <div class="col-6 col-lg-3">' . Ui::kpiCard('Open Tickets', (string) $k['open_tickets'], 'SLA status in Support analytics') . '</div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card h-100"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 fw-bold mb-0">Predictive Alerts — Top Risks</h2><a class="btn btn-sm btn-outline-primary" href="alerts.php">Open console <i class="bi bi-arrow-right"></i></a></div>';
if (!$alertsView['open']) {
    $body .= '<p class="text-secondary mb-0">No open alerts. Run a scan from the console.</p>';
} else {
    $body .= '<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Priority</th><th>Alert</th><th>Type</th><th>Score</th></tr></thead><tbody>';
    foreach (array_slice($alertsView['open'], 0, 8) as $a) {
        $body .= '<tr><td><span class="pill sev-' . e($a['severity']) . '">' . Alerts::priorityTier((int) $a['priority_score']) . '</span></td>
        <td class="small">' . e($a['title']) . '<div class="text-secondary" style="font-size:.75rem">' . e($a['message']) . '</div></td>
        <td><code class="small">' . e($a['type']) . '</code></td><td class="fw-bold">' . (int) $a['priority_score'] . '</td></tr>';
    }
    $body .= '</tbody></table></div>';
}
$body .= '</div></div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100"><div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 fw-bold mb-0">Latest Reports <span class="text-secondary fw-normal small">(14.10.9)</span></h2><a class="btn btn-sm btn-outline-primary" href="reports.php">All reports</a></div>';
foreach ($reports as $type => $snap) {
    $body .= '<div class="d-flex justify-content-between align-items-center border-bottom py-2">
        <div><div class="small fw-bold">' . e(Reports::TYPES[$type] ?? $type) . '</div>
        <div class="text-secondary" style="font-size:.75rem">' . e($snap['period_label']) . ' · ' . e($snap['generated_at']) . ' · ' . Ui::statusBadge((string) $snap['status']) . '</div></div>
        <a class="btn btn-sm btn-light" href="reports.php?view=' . (int) $snap['id'] . '">View</a></div>';
}
$body .= '</div></div>
  </div>
</div>';
echo Ui::layout('Dashboard', 'dashboard', $body, $user);
