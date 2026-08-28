<?php
// 14.8 — Automated Reports.
//   14.8.1 Daily operations snapshot   type: daily_operations_snapshot
//   14.8.2 Weekly sales report          type: weekly_sales
//   14.8.3 Revenue report               type: revenue
//   14.8.4 Project health report        type: project_health
//   14.8.5 Profitability report         type: profitability
//   14.8.6 Team performance report      type: team_performance
//   14.8.7 Support/SLA report           type: support_sla
//   14.8.8 Monthly executive report     type: monthly_executive
//   14.8.9 Historical report snapshots  generate() writes ops_report_snapshots
//   14.8.10 Report validation           validate() guards every snapshot
declare(strict_types=1);

final class Reports
{
    public const TYPES = [
        'daily_operations_snapshot' => 'Daily Operations Snapshot',
        'weekly_sales' => 'Weekly Sales Report',
        'revenue' => 'Revenue Report',
        'project_health' => 'Project Health Report',
        'profitability' => 'Profitability Report',
        'team_performance' => 'Team Performance Report',
        'support_sla' => 'Support / SLA Report',
        'monthly_executive' => 'Monthly Executive Report',
    ];

    public function __construct(private PDO $db) {}

    // ------------------------------------------------------------------
    // Generators
    // ------------------------------------------------------------------

    public function generate(string $type, string $generatedBy = 'system', ?string $now = null): array
    {
        if (!isset(self::TYPES[$type])) {
            throw new InvalidArgumentException('Unknown report type: ' . $type);
        }
        $now ??= Db::now();
        [$start, $end, $label] = $this->periodFor($type, $now);
        $payload = $this->build($type, $start, $end, $now);

        // 14.8.10 — validate before persisting; failures are stored as drafts
        $validation = $this->validate($type, $payload);
        $status = $validation['ok'] ? 'final' : 'draft';

        $ins = $this->db->prepare(
            'INSERT INTO ops_report_snapshots (report_type, period_label, period_start, period_end, generated_by, generated_at, status, payload_json, validation_ok, validation_errors, row_count)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $ins->execute([
            $type, $label, $start, $end, $generatedBy, $now, $status,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $validation['ok'] ? 1 : 0,
            $validation['errors'] ? implode('; ', $validation['errors']) : null,
            $payload['meta']['row_count'] ?? 0,
        ]);
        $payload['meta']['snapshot_id'] = (int) $this->db->lastInsertId();
        $payload['meta']['validation'] = $validation;
        return $payload;
    }

    public function periodFor(string $type, string $now): array
    {
        $ts = strtotime($now);
        switch ($type) {
            case 'daily_operations_snapshot':
                $d = date('Y-m-d', $ts);
                return [$d, $d, 'Day of ' . date('D j M Y', $ts)];
            case 'weekly_sales':
                $monday = strtotime('monday this week', $ts);
                return [date('Y-m-d', $monday), date('Y-m-d', strtotime('+6 days', $monday)), 'Week of ' . date('j M', $monday)];
            case 'monthly_executive':
                $start = date('Y-m-01', $ts);
                return [$start, date('Y-m-d', $ts), 'Month of ' . date('F Y', $ts)];
            default:
                return [date('Y-m-d', strtotime('-29 days', $ts)), date('Y-m-d', $ts), 'Last 30 days'];
        }
    }

    private function build(string $type, string $start, string $end, string $now): array
    {
        return match ($type) {
            'daily_operations_snapshot' => $this->dailySnapshot($start, $end, $now),
            'weekly_sales' => $this->weeklySales($start, $end, $now),
            'revenue' => $this->revenue($start, $end, $now),
            'project_health' => $this->projectHealth($start, $end, $now),
            'profitability' => $this->profitability($start, $end, $now),
            'team_performance' => $this->teamPerformance($start, $end, $now),
            'support_sla' => $this->supportSla($start, $end, $now),
            'monthly_executive' => $this->executive($start, $end, $now),
        };
    }

    /** 14.8.1 */
    private function dailySnapshot(string $start, string $end, string $now): array
    {
        $alerts = new Alerts($this->db);
        $openAlerts = $alerts->countsBySeverity();
        $metrics = [
            'open_alerts' => array_sum($openAlerts),
            'critical_alerts' => $openAlerts['critical'],
            'active_projects' => (int) $this->db->query("SELECT COUNT(*) FROM ops_projects WHERE status = 'active'")->fetchColumn(),
            'overdue_invoices' => (int) $this->db->query("SELECT COUNT(*) FROM ops_invoices WHERE status = 'sent' AND due_date < '" . Db::today() . "'")->fetchColumn(),
            'overdue_tasks' => (int) $this->db->query("SELECT COUNT(*) FROM ops_tasks WHERE status NOT IN ('done') AND due_date < '" . Db::today() . "'")->fetchColumn(),
            'open_tickets' => (int) $this->db->query("SELECT COUNT(*) FROM ops_tickets WHERE status IN ('open','pending')")->fetchColumn(),
            'active_leads' => (int) $this->db->query("SELECT COUNT(*) FROM ops_leads WHERE stage NOT IN ('won','lost')")->fetchColumn(),
            'pipeline_value' => (float) $this->db->query("SELECT COALESCE(SUM(value_estimate),0) FROM ops_leads WHERE stage NOT IN ('won','lost')")->fetchColumn(),
            'outstanding_invoices' => (float) $this->db->query("SELECT COALESCE(SUM(total),0) FROM ops_invoices WHERE status = 'sent'")->fetchColumn(),
        ];
        return ['meta' => ['type' => 'daily_operations_snapshot', 'row_count' => count($metrics)], 'metrics' => $metrics];
    }

    /** 14.8.2 */
    private function weeklySales(string $start, string $end, string $now): array
    {
        $leads = $this->db->query('SELECT stage, COUNT(*) n, COALESCE(SUM(value_estimate),0) v FROM ops_leads GROUP BY stage')->fetchAll();
        $created = (int) $this->db->query("SELECT COUNT(*) FROM ops_leads WHERE created_at BETWEEN '$start 00:00:00' AND '$end 23:59:59'")->fetchColumn();
        $won = (int) $this->db->query("SELECT COUNT(*) FROM ops_leads WHERE stage = 'won'")->fetchColumn();
        $lost = (int) $this->db->query("SELECT COUNT(*) FROM ops_leads WHERE stage = 'lost'")->fetchColumn();
        $decided = $won + $lost;
        $pipeline = array_sum(array_map(fn ($r) => ($r['stage'] !== 'won' && $r['stage'] !== 'lost') ? (float) $r['v'] : 0, $leads));
        return [
            'meta' => ['type' => 'weekly_sales', 'row_count' => count($leads)],
            'metrics' => [
                'new_leads_in_period' => $created,
                'active_pipeline_value' => $pipeline,
                'won_leads' => $won,
                'lost_leads' => $lost,
                'win_rate_pct' => $decided > 0 ? round($won / $decided * 100, 1) : 0.0,
            ],
            'tables' => ['leads_by_stage' => $leads],
        ];
    }

    /** 14.8.3 */
    private function revenue(string $start, string $end, string $now): array
    {
        $rows = $this->db->query(
            "SELECT substr(issue_date,1,7) AS month,
                    SUM(CASE WHEN status IN ('sent','paid') THEN total ELSE 0 END) AS invoiced,
                    SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) AS collected,
                    SUM(CASE WHEN status = 'sent' AND due_date < '" . Db::today() . "' THEN total ELSE 0 END) AS overdue
             FROM ops_invoices WHERE substr(issue_date,1,7) IS NOT NULL GROUP BY substr(issue_date,1,7) ORDER BY month"
        )->fetchAll();
        $invoiced = array_sum(array_column($rows, 'invoiced'));
        $collected = array_sum(array_column($rows, 'collected'));
        return [
            'meta' => ['type' => 'revenue', 'row_count' => count($rows)],
            'metrics' => [
                'total_invoiced' => (float) $invoiced,
                'total_collected' => (float) $collected,
                'outstanding' => (float) ($invoiced - $collected),
                'collection_rate_pct' => $invoiced > 0 ? round($collected / $invoiced * 100, 1) : 0.0,
            ],
            'tables' => ['monthly' => $rows],
        ];
    }

    /** 14.8.4 */
    private function projectHealth(string $start, string $end, string $now): array
    {
        $prof = (new Profitability($this->db))->projectProfitability();
        $active = array_values(array_filter($prof, fn ($p) => $p['status'] === 'active'));
        $overdueSql = $this->db->query("SELECT project_id, COUNT(*) n FROM ops_tasks WHERE status NOT IN ('done') AND due_date < '" . Db::today() . "' GROUP BY project_id")->fetchAll();
        $overdueTasks = [];
        foreach ($overdueSql as $r) { $overdueTasks[(int) $r['project_id']] = (int) $r['n']; }
        $rows = array_map(function ($p) use ($overdueTasks) {
            return [
                'project' => $p['name'], 'client' => $p['client'], 'budget' => $p['budget'], 'cost' => $p['cost'],
                'budget_use_pct' => $p['budget_use_pct'], 'margin_pct' => $p['margin_pct'], 'due_date' => $p['due_date'],
                'overdue_tasks' => $overdueTasks[$p['id']] ?? 0,
                'flags' => array_merge($p['is_over_budget'] ? ['over_budget'] : [], $p['is_low_margin'] ? ['low_margin'] : [], (strtotime($p['due_date']) < time() && $p['status'] === 'active') ? ['overdue'] : []),
            ];
        }, $active);
        return [
            'meta' => ['type' => 'project_health', 'row_count' => count($rows)],
            'metrics' => [
                'active_projects' => count($rows),
                'flagged_projects' => count(array_filter($rows, fn ($r) => $r['flags'])),
                'overdue_projects' => count(array_filter($rows, fn ($r) => in_array('overdue', $r['flags'], true))),
            ],
            'tables' => ['projects' => $rows],
        ];
    }

    /** 14.8.5 */
    private function profitability(string $start, string $end, string $now): array
    {
        $prof = new Profitability($this->db);
        $rows = $prof->projectProfitability();
        return [
            'meta' => ['type' => 'profitability', 'row_count' => count($rows)],
            'metrics' => $prof->kpis(),
            'tables' => ['projects' => array_map(fn ($p) => [
                'project' => $p['name'], 'client' => $p['client'], 'status' => $p['status'],
                'budget' => $p['budget'], 'cost' => $p['cost'], 'revenue' => $p['revenue'],
                'gross_profit' => $p['gross_profit'], 'margin_pct' => $p['margin_pct'],
            ], $rows)],
        ];
    }

    /** 14.8.6 */
    private function teamPerformance(string $start, string $end, string $now): array
    {
        $team = $this->db->query('SELECT id, name, role FROM ops_team ORDER BY id')->fetchAll();
        $done = $this->db->query("SELECT team_id, COUNT(*) n FROM ops_tasks WHERE status = 'done' GROUP BY team_id")->fetchAll();
        $overdue = $this->db->query("SELECT team_id, COUNT(*) n FROM ops_tasks WHERE status NOT IN ('done') AND due_date < '" . Db::today() . "' GROUP BY team_id")->fetchAll();
        $open = $this->db->query("SELECT team_id, COUNT(*) n FROM ops_tasks WHERE status NOT IN ('done') GROUP BY team_id")->fetchAll();
        $labor = $this->db->query("SELECT team_id, SUM(amount) v FROM ops_cost_entries WHERE category = 'labor' GROUP BY team_id")->fetchAll();
        $map = fn ($rows) => array_combine(array_map(fn ($r) => (int) $r['team_id'], $rows), array_map(fn ($r) => (int) $r['n'], $rows));
        $doneM = $map($done); $overdueM = $map($overdue); $openM = $map($open);
        $laborM = [];
        foreach ($labor as $r) { $laborM[(int) $r['team_id']] = (float) $r['v']; }

        $rows = array_map(fn ($m) => [
            'member' => $m['name'], 'role' => $m['role'],
            'tasks_done' => $doneM[(int) $m['id']] ?? 0,
            'tasks_open' => $openM[(int) $m['id']] ?? 0,
            'tasks_overdue' => $overdueM[(int) $m['id']] ?? 0,
            'labor_cost' => $laborM[(int) $m['id']] ?? 0.0,
        ], $team);
        return [
            'meta' => ['type' => 'team_performance', 'row_count' => count($rows)],
            'metrics' => [
                'total_done' => array_sum(array_column($rows, 'tasks_done')),
                'total_overdue' => array_sum(array_column($rows, 'tasks_overdue')),
                'total_labor_cost' => array_sum(array_column($rows, 'labor_cost')),
            ],
            'tables' => ['team' => $rows],
        ];
    }

    /** 14.8.7 */
    private function supportSla(string $start, string $end, string $now): array
    {
        $tickets = $this->db->query("SELECT id, priority, status, sla_due_at, first_response_at, resolved_at, created_at FROM ops_tickets")->fetchAll();
        $resolved = array_filter($tickets, fn ($t) => $t['resolved_at'] !== null);
        $breaches = array_filter($tickets, fn ($t) => $t['resolved_at'] === null && strtotime($t['sla_due_at']) < time() && $t['status'] !== 'resolved');
        $responseHours = [];
        foreach ($tickets as $t) {
            if ($t['first_response_at']) {
                $responseHours[] = (strtotime($t['first_response_at']) - strtotime($t['created_at'])) / 3600;
            }
        }
        $met = 0;
        foreach ($tickets as $t) {
            $due = strtotime($t['sla_due_at']);
            if (($t['resolved_at'] !== null && strtotime($t['resolved_at']) <= $due)
                || ($t['first_response_at'] !== null && strtotime($t['first_response_at']) <= $due)) {
                $met++;
            }
        }
        $total = count($tickets);
        return [
            'meta' => ['type' => 'support_sla', 'row_count' => $total],
            'metrics' => [
                'total_tickets' => $total,
                'open_tickets' => count(array_filter($tickets, fn ($t) => $t['status'] !== 'resolved')),
                'resolved_tickets' => count($resolved),
                'sla_breaches' => count($breaches),
                'sla_compliance_pct' => $total > 0 ? round($met / $total * 100, 1) : 100.0,
                'avg_first_response_hours' => $responseHours ? round(array_sum($responseHours) / count($responseHours), 1) : 0.0,
            ],
            'tables' => ['open_breaches' => array_values(array_map(fn ($t) => ['id' => $t['id'], 'priority' => $t['priority'], 'sla_due_at' => $t['sla_due_at'], 'status' => $t['status']], $breaches))],
        ];
    }

    /** 14.8.8 — composite of the other seven. */
    private function executive(string $start, string $end, string $now): array
    {
        $prof = (new Profitability($this->db))->kpis();
        $revenue = $this->revenue($start, $end, $now)['metrics'];
        $sales = $this->weeklySales($start, $end, $now)['metrics'];
        $sla = $this->supportSla($start, $end, $now)['metrics'];
        $alerts = (new Alerts($this->db))->countsBySeverity();
        return [
            'meta' => ['type' => 'monthly_executive', 'row_count' => 5],
            'metrics' => [
                'profitability' => ['company_margin_pct' => $prof['company_margin_pct'], 'gross_profit' => $prof['gross_profit'], 'low_margin_count' => $prof['low_margin_count'], 'over_budget_count' => $prof['over_budget_count']],
                'revenue' => $revenue,
                'sales' => $sales,
                'support' => $sla,
                'alerts' => $alerts,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // 14.8.9 — historical snapshots
    // ------------------------------------------------------------------
    public function snapshots(string $type = '', int $limit = 100): array
    {
        $sql = 'SELECT id, report_type, period_label, period_start, period_end, generated_by, generated_at, status, validation_ok, validation_errors, row_count
                FROM ops_report_snapshots';
        $params = [];
        if ($type !== '') {
            $sql .= ' WHERE report_type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY generated_at DESC, id DESC LIMIT ' . max(1, min(500, $limit));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function snapshot(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ops_report_snapshots WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function latestByType(): array
    {
        $out = [];
        foreach ($this->snapshots('', 500) as $s) {
            if (!isset($out[$s['report_type']])) {
                $out[$s['report_type']] = $s;
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // 14.8.10 — validation rules per report type
    // ------------------------------------------------------------------
    public function validate(string $type, array $payload): array
    {
        $errors = [];
        if (!isset(self::TYPES[$type])) {
            return ['ok' => false, 'errors' => ['Unknown report type']];
        }
        if (!isset($payload['meta']['type']) || $payload['meta']['type'] !== $type) {
            $errors[] = 'payload meta.type mismatch';
        }
        if (!isset($payload['metrics']) || !is_array($payload['metrics']) || !$payload['metrics']) {
            $errors[] = 'metrics section missing or empty';
        }
        if (($payload['meta']['row_count'] ?? 0) < 0) {
            $errors[] = 'row_count cannot be negative';
        }

        $numericChecks = [
            'daily_operations_snapshot' => ['open_alerts', 'critical_alerts', 'active_projects', 'overdue_invoices', 'overdue_tasks', 'open_tickets'],
            'weekly_sales' => ['new_leads_in_period', 'won_leads', 'lost_leads', 'win_rate_pct'],
            'revenue' => ['total_invoiced', 'total_collected', 'collection_rate_pct'],
            'project_health' => ['active_projects'],
            'profitability' => ['company_margin_pct', 'gross_profit'],
            'team_performance' => ['total_done'],
            'support_sla' => ['total_tickets', 'sla_compliance_pct'],
            'monthly_executive' => [],
        ];
        foreach ($numericChecks[$type] ?? [] as $field) {
            $value = $payload['metrics'][$field] ?? null;
            if ($value === null) {
                $errors[] = "missing metric: $field";
            } elseif (!is_numeric($value)) {
                $errors[] = "metric $field is not numeric";
            } elseif ((float) $value < 0 && !in_array($field, ['gross_profit', 'company_margin_pct', 'avg_project_margin_pct'], true)) {
                $errors[] = "metric $field is negative";
            }
        }
        // cross-checks
        if ($type === 'revenue' && isset($payload['metrics']['collection_rate_pct']) && (float) $payload['metrics']['collection_rate_pct'] > 100.01) {
            $errors[] = 'collection rate above 100%';
        }
        if ($type === 'project_health') {
            foreach ($payload['tables']['projects'] ?? [] as $p) {
                if (($p['budget'] ?? 0) > 0 && ($p['cost'] ?? 0) / $p['budget'] > 10) {
                    $errors[] = 'implausible budget usage >1000%: ' . ($p['project'] ?? '?');
                }
            }
        }
        return ['ok' => !$errors, 'errors' => $errors];
    }
}
