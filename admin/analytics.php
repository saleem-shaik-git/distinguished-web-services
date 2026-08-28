<?php
declare(strict_types=1);
// 14.10 — Executive BI analytics: sales, finance, projects, team, support,
// profitability and the predictive-alerts trend view.
require_once __DIR__ . '/../src/bootstrap.php';
$user = Auth::requireLogin();
$pdo = ops_db();
$an = new Analytics($pdo);

$tab = (string) ($_GET['tab'] ?? 'sales');
$tabs = ['sales' => 'Sales', 'finance' => 'Finance', 'projects' => 'Projects', 'team' => 'Team', 'support' => 'Support', 'profit' => 'Profitability', 'pred' => 'Predictive Alerts'];
$body = '<h1 class="h4 fw-bold mb-4">BI Analytics <span class="text-secondary fs-6 fw-normal">· 14.10</span></h1>
<div class="btn-group flex-wrap gap-1 mb-4">'
. implode('', array_map(fn ($k, $label) => '<a class="btn btn-sm ' . ($tab === $k ? 'btn-dark' : 'btn-light') . '" href="analytics.php?tab=' . $k . '">' . $label . '</a>', array_keys($tabs), $tabs)) . '</div>';

$barRow = function (string $label, float $value, float $max, string $color = '#2278d2', string $right = ''): string {
    $w = $max > 0 ? max(1.5, $value / $max * 100) : 0;
    return '<div class="mb-2"><div class="d-flex justify-content-between small"><span>' . e($label) . '</span><strong>' . ($right !== '' ? $right : ops_money($value)) . '</strong></div>
    <div class="bar"><span style="width:' . $w . '%;background:' . $color . '"></span></div></div>';
};

switch ($tab) {
    case 'sales':
        $s = $an->sales();
        $max = max(1, ...array_map(fn ($r) => (float) $r['v'], $s['by_stage']));
        $body .= '<div class="row g-3 mb-4">
          <div class="col-6 col-lg-3">' . Ui::kpiCard('Pipeline', ops_money($s['pipeline_value'])) . '</div>
          <div class="col-6 col-lg-3">' . Ui::kpiCard('Win Rate', ops_pct($s['win_rate_pct'])) . '</div>
          <div class="col-6 col-lg-3">' . Ui::kpiCard('Avg Deal (won)', ops_money($s['avg_deal_size'])) . '</div>
        </div><div class="card"><div class="card-body"><h2 class="h6 fw-bold">Pipeline by stage</h2>'
        . implode('', array_map(fn ($r) => $barRow($r['stage'] . ' (' . (int) $r['n'] . ')', (float) $r['v'], $max, in_array($r['stage'], ['won'], true) ? '#12b76a' : (in_array($r['stage'], ['lost'], true) ? '#d92d20' : '#2278d2')), $s['by_stage']))
        . '</div></div>';
        break;

    case 'finance':
        $f = $an->finance();
        $max = max(1.0, ...array_map(fn ($r) => max((float) $r['invoiced'], (float) $r['collected']), $f['monthly']), ...array_values($f['aging']));
        $body .= '<div class="card mb-4"><div class="card-body"><h2 class="h6 fw-bold">Invoiced vs collected (monthly)</h2>'
        . implode('', array_map(fn ($r) => $barRow(date('M Y', strtotime($r['m'] . '-01')) . ' — collected ' . ops_money($r['collected']) . ' of ' . ops_money($r['invoiced']), (float) $r['invoiced'], $max, '#2278d2', ops_money($r['invoiced'])), $f['monthly']))
        . '</div></div><div class="card"><div class="card-body"><h2 class="h6 fw-bold">AR aging (outstanding)</h2>'
        . $barRow('Current (not yet due)', $f['aging']['current'], $max, '#12b76a')
        . $barRow('1–30 days overdue', $f['aging']['1_30'], $max, '#f79009')
        . $barRow('31–60 days overdue', $f['aging']['31_60'], $max, '#f79009')
        . $barRow('60+ days overdue', $f['aging']['60_plus'], $max, '#d92d20')
        . '</div></div>';
        break;

    case 'projects':
        $p = $an->projects();
        $maxN = max(1, ...array_map(fn ($r) => (int) $r['n'], $p['by_status']));
        $body .= '<div class="card mb-4"><div class="card-body"><h2 class="h6 fw-bold">Projects by status</h2>'
        . implode('', array_map(fn ($r) => $barRow(ucfirst((string) $r['status']), (float) $r['n'], $maxN, '#2278d2', (string) $r['n']), $p['by_status'])) . '</div></div>
        <div class="card"><div class="card-body"><h2 class="h6 fw-bold">Budget vs cost</h2><div class="table-responsive"><table class="table table-sm small">
        <thead><tr><th>Project</th><th class="text-end">Budget</th><th class="text-end">Cost</th><th>Utilisation</th></tr></thead><tbody>'
        . implode('', array_map(function ($r) {
            $pct = $r['budget_use_pct'];
            $color = $pct > 100 ? '#d92d20' : ($pct > 85 ? '#f79009' : '#12b76a');
            return '<tr><td>' . e($r['name']) . '</td><td class="text-end">' . ops_money($r['budget']) . '</td><td class="text-end">' . ops_money($r['cost']) . '</td>
            <td style="min-width:140px"><div class="bar"><span style="width:' . min(100, max(2, $pct)) . '%;background:' . $color . '"></span></div><span class="text-secondary">' . ops_pct($pct) . '</span></td></tr>';
        }, $p['budget_vs_cost'])) . '</tbody></table></div></div></div>';
        break;

    case 'team':
        $t = $an->team();
        $body .= '<div class="card"><div class="card-body"><h2 class="h6 fw-bold">Team analytics (14.10.5)</h2>
        <div class="table-responsive"><table class="table table-sm small align-middle">
        <thead><tr><th>Member</th><th>Role</th><th class="text-end">Done</th><th class="text-end">Open</th><th class="text-end">Overdue</th><th class="text-end">Labor cost</th></tr></thead><tbody>'
        . implode('', array_map(fn ($m) => '<tr><td class="fw-bold">' . e($m['name']) . '</td><td>' . e((string) $m['role']) . '</td>
          <td class="text-end">' . (int) $m['done'] . '</td><td class="text-end">' . (int) $m['open_tasks'] . '</td>
          <td class="text-end ' . ((int) $m['overdue'] ? 'text-danger fw-bold' : '') . '">' . (int) $m['overdue'] . '</td>
          <td class="text-end">' . ops_money((float) $m['labor_cost']) . '</td></tr>', $t)) . '</tbody></table></div></div></div>';
        break;

    case 'support':
        $s = $an->support();
        $body .= '<div class="card mb-4"><div class="card-body"><h2 class="h6 fw-bold">Open tickets by priority</h2><div class="d-flex gap-3 flex-wrap">'
        . implode('', array_map(fn ($r) => '<div class="text-center"><div class="display-6 fw-bold">' . (int) $r['n'] . '</div><div class="kpi-label">' . e($r['priority']) . '</div></div>', $s['open_by_priority']))
        . ($s['open_by_priority'] ? '' : '<span class="text-secondary">No open tickets.</span>') . '</div></div></div>
        <div class="card"><div class="card-body"><h2 class="h6 fw-bold">SLA queue</h2><div class="table-responsive"><table class="table table-sm small">
        <thead><tr><th>#</th><th>Subject</th><th>Client</th><th>Priority</th><th>Status</th><th>SLA due</th><th>State</th></tr></thead><tbody>'
        . implode('', array_map(function ($t) {
            $hoursLeft = (strtotime($t['sla_due_at']) - time()) / 3600;
            $state = $t['first_response_at'] ? '<span class="pill sev-info">responded</span>' : ($hoursLeft <= 0 ? '<span class="pill sev-critical">BREACHED</span>' : ($hoursLeft <= 3 ? '<span class="pill sev-warning">AT RISK</span>' : '<span class="text-secondary small">on track</span>'));
            return '<tr><td>' . (int) $t['id'] . '</td><td>' . e($t['subject']) . '</td><td>' . e($t['client']) . '</td><td>' . e($t['priority']) . '</td><td>' . e($t['status']) . '</td><td class="text-secondary">' . e($t['sla_due_at']) . '</td><td>' . $state . '</td></tr>';
        }, $s['open_tickets'])) . '</tbody></table></div></div></div>';
        break;

    case 'profit':
        $rows = $an->profitability();
        usort($rows, fn ($a, $b) => $b['margin_pct'] <=> $a['margin_pct']);
        $body .= '<div class="card"><div class="card-body"><h2 class="h6 fw-bold">Profitability ranking (14.10.7)</h2>
        <div class="table-responsive"><table class="table table-sm small align-middle">
        <thead><tr><th>#</th><th>Project</th><th class="text-end">Revenue</th><th class="text-end">Cost</th><th class="text-end">Profit</th><th>Margin</th></tr></thead><tbody>'
        . implode('', array_map(fn ($i, $r) => '<tr><td class="text-secondary">' . ($i + 1) . '</td><td class="fw-bold">' . e($r['name']) . '</td>
          <td class="text-end">' . ops_money($r['invoiced']) . '</td><td class="text-end">' . ops_money($r['cost']) . '</td>
          <td class="text-end" style="color:' . ($r['gross_profit'] >= 0 ? '#067647' : '#b42318') . '">' . ops_money($r['gross_profit']) . '</td>
          <td class="fw-bold" style="color:' . ($r['margin_pct'] < OPS_LOW_MARGIN_THRESHOLD ? '#b54708' : '#067647') . '">' . ops_pct($r['margin_pct']) . '</td></tr>', array_keys($rows), $rows))
        . '</tbody></table></div></div></div>';
        break;

    case 'pred':
        $v = $an->alertsView();
        $body .= '<div class="card"><div class="card-body"><h2 class="h6 fw-bold">Alert trend — last 7 days (14.10.8)</h2>'
        . (empty($v['trend_7d']) ? '<p class="text-secondary small mb-0">No alert activity recorded in the window.</p>' : '<div class="table-responsive"><table class="table table-sm small">
          <thead><tr><th>Type</th><th class="text-end">Distinct alerts</th><th class="text-end">Total triggers</th></tr></thead><tbody>'
          . implode('', array_map(fn ($r) => '<tr><td><code>' . e($r['type']) . '</code></td><td class="text-end">' . (int) $r['n'] . '</td><td class="text-end fw-bold">' . (int) $r['triggers'] . '</td></tr>', $v['trend_7d']))
          . '</tbody></table></div>') . '</div></div>';
        break;
}
echo Ui::layout('BI Analytics', 'analytics', $body, $user);
