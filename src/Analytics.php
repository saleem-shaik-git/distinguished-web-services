<?php
// 14.10 — Executive BI data providers (feeds the admin dashboards).
//   14.10.1 Executive KPI dashboard   execKpis()
//   14.10.2 Sales analytics           sales()
//   14.10.3 Finance analytics         finance()
//   14.10.4 Project analytics         projects()
//   14.10.5 Team analytics            team()
//   14.10.6 Support analytics         support()
//   14.10.7 Profitability analytics   profitability()
//   14.10.8 Predictive alerts view    alertsView()
//   14.10.9 Reports integration       reportsIntegration()
//  14.10.10 Final regression test     tests/run.php + admin selftest
declare(strict_types=1);

final class Analytics
{
    public function __construct(private PDO $db) {}

    /** 14.10.1 */
    public function execKpis(): array
    {
        $today = Db::today();
        $monthStart = date('Y-m-01');
        return [
            'revenue_mtd_invoiced' => (float) $this->db->query("SELECT COALESCE(SUM(total),0) FROM ops_invoices WHERE status IN ('sent','paid') AND issue_date >= '$monthStart'")->fetchColumn(),
            'revenue_mtd_collected' => (float) $this->db->query("SELECT COALESCE(SUM(total),0) FROM ops_invoices WHERE status = 'paid' AND issue_date >= '$monthStart'")->fetchColumn(),
            'outstanding_total' => (float) $this->db->query("SELECT COALESCE(SUM(total),0) FROM ops_invoices WHERE status = 'sent'")->fetchColumn(),
            'overdue_invoices' => (int) $this->db->query("SELECT COUNT(*) FROM ops_invoices WHERE status = 'sent' AND due_date < '$today'")->fetchColumn(),
            'active_projects' => (int) $this->db->query("SELECT COUNT(*) FROM ops_projects WHERE status = 'active'")->fetchColumn(),
            'overdue_projects' => (int) $this->db->query("SELECT COUNT(*) FROM ops_projects WHERE status IN ('active','on_hold') AND due_date < '$today'")->fetchColumn(),
            'overdue_tasks' => (int) $this->db->query("SELECT COUNT(*) FROM ops_tasks WHERE status NOT IN ('done') AND due_date < '$today'")->fetchColumn(),
            'open_tickets' => (int) $this->db->query("SELECT COUNT(*) FROM ops_tickets WHERE status IN ('open','pending')")->fetchColumn(),
            'active_leads' => (int) $this->db->query("SELECT COUNT(*) FROM ops_leads WHERE stage NOT IN ('won','lost')")->fetchColumn(),
            'pipeline_value' => (float) $this->db->query("SELECT COALESCE(SUM(value_estimate),0) FROM ops_leads WHERE stage NOT IN ('won','lost')")->fetchColumn(),
            'alerts' => (new Alerts($this->db))->countsBySeverity(),
            'profitability' => (new Profitability($this->db))->kpis(),
        ];
    }

    /** 14.10.2 — leads by stage + monthly acquisition series. */
    public function sales(): array
    {
        $byStage = $this->db->query('SELECT stage, COUNT(*) n, COALESCE(SUM(value_estimate),0) v FROM ops_leads GROUP BY stage ORDER BY n DESC')->fetchAll();
        $monthly = $this->db->query("SELECT substr(created_at,1,7) m, COUNT(*) n FROM ops_leads GROUP BY substr(created_at,1,7) ORDER BY m")->fetchAll();
        $won = (int) $this->db->query("SELECT COUNT(*) FROM ops_leads WHERE stage = 'won'")->fetchColumn();
        $lost = (int) $this->db->query("SELECT COUNT(*) FROM ops_leads WHERE stage = 'lost'")->fetchColumn();
        $decided = $won + $lost;
        return [
            'by_stage' => $byStage,
            'monthly' => $monthly,
            'win_rate_pct' => $decided ? round($won / $decided * 100, 1) : 0.0,
            'pipeline_value' => (float) $this->db->query("SELECT COALESCE(SUM(value_estimate),0) FROM ops_leads WHERE stage NOT IN ('won','lost')")->fetchColumn(),
            'avg_deal_size' => $won ? round((float) $this->db->query("SELECT COALESCE(SUM(value_estimate),0) FROM ops_leads WHERE stage = 'won'")->fetchColumn() / $won) : 0.0,
        ];
    }

    /** 14.10.3 — invoiced / collected / overdue by month + AR aging. */
    public function finance(): array
    {
        $monthly = $this->db->query(
            "SELECT substr(issue_date,1,7) m,
                    SUM(CASE WHEN status IN ('sent','paid') THEN total ELSE 0 END) invoiced,
                    SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) collected
             FROM ops_invoices GROUP BY substr(issue_date,1,7) ORDER BY m"
        )->fetchAll();
        $aging = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '60_plus' => 0.0];
        $open = $this->db->query("SELECT total, due_date FROM ops_invoices WHERE status = 'sent'")->fetchAll();
        foreach ($open as $i) {
            $days = (int) floor((strtotime(Db::today()) - strtotime($i['due_date'])) / 86400);
            if ($days <= 0) { $aging['current'] += (float) $i['total']; }
            elseif ($days <= 30) { $aging['1_30'] += (float) $i['total']; }
            elseif ($days <= 60) { $aging['31_60'] += (float) $i['total']; }
            else { $aging['60_plus'] += (float) $i['total']; }
        }
        $costs = $this->db->query(
            "SELECT substr(cost_date,1,7) m, SUM(amount) v FROM ops_cost_entries GROUP BY substr(cost_date,1,7) ORDER BY m"
        )->fetchAll();
        return ['monthly' => $monthly, 'aging' => $aging, 'costs_by_month' => $costs];
    }

    /** 14.10.4 */
    public function projects(): array
    {
        $byStatus = $this->db->query('SELECT status, COUNT(*) n FROM ops_projects GROUP BY status')->fetchAll();
        $health = (new Reports($this->db));
        $rows = (new Profitability($this->db))->projectProfitability();
        return ['by_status' => $byStatus, 'budget_vs_cost' => $rows];
    }

    /** 14.10.5 */
    public function team(): array
    {
        return $this->db->query(
            "SELECT m.id, m.name, m.role,
                (SELECT COUNT(*) FROM ops_tasks t WHERE t.team_id = m.id AND t.status = 'done') AS done,
                (SELECT COUNT(*) FROM ops_tasks t WHERE t.team_id = m.id AND t.status != 'done') AS open_tasks,
                (SELECT COUNT(*) FROM ops_tasks t WHERE t.team_id = m.id AND t.status != 'done' AND t.due_date < '" . Db::today() . "') AS overdue,
                (SELECT COALESCE(SUM(c.amount),0) FROM ops_cost_entries c WHERE c.team_id = m.id AND c.category = 'labor') AS labor_cost
             FROM ops_team m ORDER BY m.id"
        )->fetchAll();
    }

    /** 14.10.6 */
    public function support(): array
    {
        $byPriority = $this->db->query("SELECT priority, COUNT(*) n FROM ops_tickets WHERE status IN ('open','pending') GROUP BY priority")->fetchAll();
        $open = $this->db->query(
            "SELECT t.id, t.subject, t.priority, t.status, t.sla_due_at, t.first_response_at, c.name client
             FROM ops_tickets t JOIN ops_clients c ON c.id = t.client_id
             WHERE t.status IN ('open','pending') ORDER BY t.sla_due_at"
        )->fetchAll();
        $resolved = (int) $this->db->query("SELECT COUNT(*) FROM ops_tickets WHERE status = 'resolved'")->fetchColumn();
        return ['open_by_priority' => $byPriority, 'open_tickets' => $open, 'resolved_count' => $resolved];
    }

    /** 14.10.7 */
    public function profitability(): array
    {
        return (new Profitability($this->db))->projectProfitability();
    }

    /** 14.10.8 — open alerts + trigger trend (most-retriggered). */
    public function alertsView(): array
    {
        $open = (new Alerts($this->db))->open();
        $trend = $this->db->query(
            "SELECT type, COUNT(*) n, SUM(trigger_count) triggers FROM ops_alerts
             WHERE last_triggered_at >= '" . date('Y-m-d', strtotime('-7 days')) . "'
             GROUP BY type ORDER BY triggers DESC"
        )->fetchAll();
        return ['open' => $open, 'trend_7d' => $trend];
    }

    /** 14.10.9 — latest snapshot of each report type for the dashboard. */
    public function reportsIntegration(): array
    {
        return (new Reports($this->db))->latestByType();
    }
}
