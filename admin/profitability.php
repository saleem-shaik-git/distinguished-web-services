<?php
declare(strict_types=1);
// 14.6 — Cost ledger & profitability views.
require_once __DIR__ . '/../src/bootstrap.php';
$user = Auth::requireLogin();
$pdo = ops_db();
$prof = new Profitability($pdo);
$kpis = $prof->kpis();
$rows = $prof->projectProfitability();
$low = $prof->lowMargin();
$over = $prof->overBudget();
$selected = isset($_GET['project']) ? (int) $_GET['project'] : (int) ($rows[0]['id'] ?? 0);
$ledger = $selected ? $prof->ledger($selected) : [];
$summary = $selected ? $prof->ledgerSummary($selected) : [];

$budgetBar = fn (float $pct): string => '<div class="bar" style="min-width:110px"><span style="width:' . min(100, max(2, $pct)) . '%;background:' . ($pct > 100 ? '#d92d20' : ($pct > 85 ? '#f79009' : '#12b76a')) . '"></span></div><span class="small text-secondary">' . ops_pct($pct) . '</span>';

$body = '
<h1 class="h4 fw-bold mb-4">Cost &amp; Profitability <span class="text-secondary fs-6 fw-normal">· 14.6</span></h1>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">' . Ui::kpiCard('Company Margin', ops_pct($kpis['company_margin_pct']), 'avg project ' . ops_pct($kpis['avg_project_margin_pct']), $kpis['company_margin_pct'] >= OPS_LOW_MARGIN_THRESHOLD ? 'green' : 'amber') . '</div>
  <div class="col-6 col-lg-3">' . Ui::kpiCard('Gross Profit', ops_money($kpis['gross_profit']), ops_money($kpis['total_revenue']) . ' revenue − ' . ops_money($kpis['total_cost']) . ' cost', 'blue') . '</div>
  <div class="col-6 col-lg-3">' . Ui::kpiCard('Low-margin Projects', (string) $kpis['low_margin_count'], 'margin &lt; ' . OPS_LOW_MARGIN_THRESHOLD . '% (14.6.4)', $low ? 'red' : 'green') . '</div>
  <div class="col-6 col-lg-3">' . Ui::kpiCard('Over-budget Projects', (string) $kpis['over_budget_count'], 'cost &gt; budget (14.6.5)', $over ? 'red' : 'green') . '</div>
</div>

<div class="card mb-4"><div class="card-body">
<h2 class="h6 fw-bold">Project profitability view <span class="text-secondary fw-normal small">(14.6.2 / 14.6.3 KPIs)</span></h2>
<div class="table-responsive"><table class="table table-hover align-middle">
<thead><tr><th>Project</th><th>Status</th><th class="text-end">Budget</th><th class="text-end">Cost</th><th class="text-end">Revenue (contract)</th><th class="text-end">Gross profit</th><th>Margin</th><th>Budget use</th><th>Flags</th></tr></thead><tbody>';
foreach ($rows as $r) {
    $flags = ($r['is_low_margin'] ? '<span class="pill sev-warning">LOW MARGIN</span> ' : '') . ($r['is_over_budget'] ? '<span class="pill sev-critical">OVER BUDGET</span>' : '');
    $body .= '<tr' . ($r['id'] === $selected ? ' class="table-active"' : '') . '>
      <td class="small"><a class="fw-bold text-decoration-none" href="profitability.php?project=' . $r['id'] . '">' . e($r['name']) . '</a><div class="text-secondary" style="font-size:.75rem">' . e($r['client']) . '</div></td>
      <td>' . Ui::statusBadge((string) $r['status']) . '</td>
      <td class="text-end small">' . ops_money($r['budget']) . '</td>
      <td class="text-end small">' . ops_money($r['cost']) . '</td>
      <td class="text-end small">' . ops_money($r['revenue']) . '</td>
      <td class="text-end small fw-bold" style="color:' . ($r['gross_profit'] >= 0 ? '#067647' : '#b42318') . '">' . ops_money($r['gross_profit']) . '</td>
      <td class="small fw-bold">' . ops_pct($r['margin_pct']) . '</td>
      <td>' . $budgetBar((float) $r['budget_use_pct']) . '</td>
      <td>' . ($flags ?: '<span class="text-secondary small">healthy</span>') . '</td></tr>';
}
$body .= '</tbody></table></div></div></div>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-body">
      <h2 class="h6 fw-bold">Low-margin detection <span class="text-secondary fw-normal small">(14.6.4)</span></h2>'
      . ($low ? '<div class="table-responsive"><table class="table table-sm small"><thead><tr><th>Project</th><th class="text-end">Margin</th></tr></thead><tbody>'
        . implode('', array_map(fn ($p) => '<tr><td>' . e($p['name']) . '</td><td class="text-end fw-bold" style="color:#b54708">' . ops_pct($p['margin_pct']) . '</td></tr>', $low))
        . '</tbody></table></div>' : '<p class="small text-secondary mb-0">No projects below the ' . OPS_LOW_MARGIN_THRESHOLD . '% threshold.</p>') . '
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-body">
      <h2 class="h6 fw-bold">Over-budget detection <span class="text-secondary fw-normal small">(14.6.5)</span></h2>'
      . ($over ? '<div class="table-responsive"><table class="table table-sm small"><thead><tr><th>Project</th><th class="text-end">Overrun</th></tr></thead><tbody>'
        . implode('', array_map(fn ($p) => '<tr><td>' . e($p['name']) . '</td><td class="text-end fw-bold" style="color:#b42318">' . ops_money($p['cost'] - $p['budget']) . '</td></tr>', $over))
        . '</tbody></table></div>' : '<p class="small text-secondary mb-0">No projects over budget.</p>') . '
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-body">
      <h2 class="h6 fw-bold">Cost ledger <span class="text-secondary fw-normal small">(14.6.1)</span> — ' . ($selected ? e((string) ($rows[array_search($selected, array_column($rows, 'id'))]['name'] ?? '')) : 'no project') . '</h2>'
      . ($summary ? '<ul class="list-group list-group-flush small mb-2">'
        . implode('', array_map(fn ($c) => '<li class="list-group-item d-flex justify-content-between bg-transparent px-0"><span>' . e((string) $c['category']) . ' <span class="text-secondary">(' . (int) $c['entries'] . ')</span></span><strong>' . ops_money((float) $c['total']) . '</strong></li>', $summary['by_category']))
        . '<li class="list-group-item d-flex justify-content-between bg-transparent px-0 fw-bold"><span>Total</span><span>' . ops_money($summary['total_cost']) . '</span></li></ul>' : '<p class="small text-secondary mb-0">No cost entries.</p>') . '
      ' . ($ledger ? '<details><summary class="small text-secondary">All ' . count($ledger) . ' entries</summary><div class="table-responsive mt-2"><table class="table table-sm small"><thead><tr><th>Date</th><th>Category</th><th>Description</th><th class="text-end">Amount</th></tr></thead><tbody>'
        . implode('', array_map(fn ($l) => '<tr><td class="text-secondary">' . e((string) $l['cost_date']) . '</td><td>' . e((string) $l['category']) . '</td><td>' . e((string) $l['description']) . '</td><td class="text-end">' . ops_money((float) $l['amount']) . '</td></tr>', $ledger))
        . '</tbody></table></div></details>' : '') . '
    </div></div>
  </div>
</div>';
echo Ui::layout('Cost & Profitability', 'profitability', $body, $user);
