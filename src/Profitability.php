<?php
// 14.6 — Project cost ledger & profitability analytics.
//  14.6.1 Project cost ledger            — ledger()/ledgerSummary() (data via ops_cost_entries)
//  14.6.2 Project profitability view     — projectProfitability()
//  14.6.3 Profitability KPI calculations — kpis()
//  14.6.4 Low-margin project detection   — lowMargin()
//  14.6.5 Over-budget project detection  — overBudget()
declare(strict_types=1);

final class Profitability
{
    public function __construct(private PDO $db) {}

    /** 14.6.1 — raw ledger rows for one project. */
    public function ledger(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ce.id, ce.category, ce.description, ce.amount, ce.cost_date, ce.team_id, t.name AS team_name
             FROM ops_cost_entries ce LEFT JOIN ops_team t ON t.id = ce.team_id
             WHERE ce.project_id = ? ORDER BY ce.cost_date DESC, ce.id DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    /** 14.6.1 — ledger totals grouped by category for one project. */
    public function ledgerSummary(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT category, COUNT(*) AS entries, SUM(amount) AS total
             FROM ops_cost_entries WHERE project_id = ? GROUP BY category ORDER BY total DESC'
        );
        $stmt->execute([$projectId]);
        $rows = $stmt->fetchAll();
        $total = 0.0;
        foreach ($rows as $r) {
            $total += (float) $r['total'];
        }
        return ['by_category' => $rows, 'total_cost' => $total, 'entry_count' => array_sum(array_column($rows, 'entries'))];
    }

    /** 14.6.2 — profitability view across projects (revenue = invoiced, fallback budget). */
    public function projectProfitability(): array
    {
        $projects = $this->db->query(
            "SELECT p.id, p.name, p.status, p.budget_amount, p.due_date, c.name AS client_name
             FROM ops_projects p LEFT JOIN ops_clients c ON c.id = p.client_id
             ORDER BY CASE WHEN p.status = 'active' THEN 0 ELSE 1 END, p.id"
        )->fetchAll();

        $costs = $this->db->query(
            'SELECT project_id, SUM(amount) AS cost, COUNT(*) AS entries FROM ops_cost_entries GROUP BY project_id'
        )->fetchAll();
        $costMap = [];
        foreach ($costs as $c) { $costMap[(int) $c['project_id']] = $c; }

        $invoiced = $this->db->query(
            "SELECT project_id, SUM(total) AS invoiced FROM ops_invoices
             WHERE status IN ('sent','paid') AND project_id IS NOT NULL GROUP BY project_id"
        )->fetchAll();
        $invMap = [];
        foreach ($invoiced as $i) { $invMap[(int) $i['project_id']] = (float) $i['invoiced']; }

        $out = [];
        foreach ($projects as $p) {
            $pid = (int) $p['id'];
            $budget = (float) $p['budget_amount'];
            $cost = isset($costMap[$pid]) ? (float) $costMap[$pid]['cost'] : 0.0;
            $invoiced = $invMap[$pid] ?? 0.0;
            // Revenue = contract value (budget), or invoiced total when
            // change-orders pushed invoicing above the original budget.
            $revenue = max($budget, $invoiced);
            $profit = $revenue - $cost;
            $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0.0;
            $budgetUse = $budget > 0 ? ($cost / $budget) * 100 : 0.0;
            $out[] = [
                'id' => $pid,
                'name' => $p['name'],
                'client' => $p['client_name'] ?? '—',
                'status' => $p['status'],
                'budget' => $budget,
                'cost' => $cost,
                'cost_entries' => isset($costMap[$pid]) ? (int) $costMap[$pid]['entries'] : 0,
                'invoiced' => $invoiced,
                'revenue' => $revenue,
                'gross_profit' => $profit,
                'margin_pct' => round($margin, 2),
                'budget_use_pct' => round($budgetUse, 2),
                'is_low_margin' => $margin < OPS_LOW_MARGIN_THRESHOLD,
                'is_over_budget' => $budget > 0 && $cost > $budget,
                'due_date' => $p['due_date'],
            ];
        }
        return $out;
    }

    /** 14.6.3 — company-level profitability KPIs. */
    public function kpis(): array
    {
        $rows = $this->projectProfitability();
        $revenue = array_sum(array_column($rows, 'revenue'));
        $cost = array_sum(array_column($rows, 'cost'));
        $profit = $revenue - $cost;
        $margins = array_filter(array_column($rows, 'margin_pct'), fn ($m) => $m > -1000);
        $low = $this->lowMargin($rows);
        $over = $this->overBudget($rows);

        return [
            'projects_tracked' => count($rows),
            'active_projects' => count(array_filter($rows, fn ($r) => $r['status'] === 'active')),
            'total_revenue' => $revenue,
            'total_cost' => $cost,
            'gross_profit' => $profit,
            'company_margin_pct' => $revenue > 0 ? round($profit / $revenue * 100, 2) : 0.0,
            'avg_project_margin_pct' => $margins ? round(array_sum($margins) / count($margins), 2) : 0.0,
            'best_project' => $rows ? $this->bestBy($rows) : null,
            'worst_project' => $rows ? $this->worstBy($rows) : null,
            'low_margin_count' => count($low),
            'over_budget_count' => count($over),
            'total_budget' => array_sum(array_column($rows, 'budget')),
        ];
    }

    /** 14.6.4 — low-margin detection (margin % below OPS_LOW_MARGIN_THRESHOLD). */
    public function lowMargin(?array $rows = null): array
    {
        $rows ??= $this->projectProfitability();
        return array_values(array_filter($rows, fn ($r) => $r['is_low_margin']));
    }

    /** 14.6.5 — over-budget detection (costs exceed budget). */
    public function overBudget(?array $rows = null): array
    {
        $rows ??= $this->projectProfitability();
        return array_values(array_filter($rows, fn ($r) => $r['is_over_budget']));
    }

    private function bestBy(array $rows): array
    {
        usort($rows, fn ($a, $b) => $b['margin_pct'] <=> $a['margin_pct']);
        return ['name' => $rows[0]['name'], 'margin_pct' => $rows[0]['margin_pct']];
    }

    private function worstBy(array $rows): array
    {
        usort($rows, fn ($a, $b) => $a['margin_pct'] <=> $b['margin_pct']);
        return ['name' => $rows[0]['name'], 'margin_pct' => $rows[0]['margin_pct']];
    }
}
