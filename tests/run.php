<?php
// Regression & acceptance suite for the 14.6–14.10 operations suite.
// Covers every numbered spec item. Run via CLI:  php tests/run.php
// or from the admin console: admin/selftest.php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !defined('OPS_WEBTEST') && !defined('OPS_TESTDRIVER')) {
    http_response_code(403);
    exit('CLI or admin console only.');
}

require_once __DIR__ . '/../src/bootstrap.php';

final class OpsTestRunner
{
    public array $results = [];
    private PDO $db;

    public function __construct()
    {
        // Dedicated throwaway database — never touches production data.
        @unlink(OPS_TEST_SQLITE_PATH);
        $this->db = Db::connect('sqlite', OPS_TEST_SQLITE_PATH);
        Schema::migrate($this->db, 'sqlite');
        Seed::run($this->db);
    }

    public function db(): PDO
    {
        return $this->db;
    }

    public function assert(bool $cond, string $message): void
    {
        if (!$cond) {
            throw new RuntimeException('ASSERT FAILED: ' . $message);
        }
    }

    public function approx(float $a, float $b, float $tol = 0.51): bool
    {
        return abs($a - $b) <= $tol;
    }

    public function run(): array
    {
        $groups = [
            '14.6 Project Cost Ledger & Profitability' => [$this, 'g_profitability'],
            '14.7 Predictive Risk & Alerts' => [$this, 'g_alerts'],
            '14.8 Automated Reports' => [$this, 'g_reports'],
            '14.9 Automation Engine' => [$this, 'g_automation'],
            '14.10 Executive BI & Production Regression' => [$this, 'g_bi'],
        ];
        foreach ($groups as $group => $fn) {
            foreach ($this->cases($fn) as $id => $case) {
                try {
                    ($case['fn'])();
                    $this->results[] = ['id' => $id, 'group' => $group, 'name' => $case['name'], 'ok' => true, 'error' => null];
                } catch (Throwable $e) {
                    $this->results[] = ['id' => $id, 'group' => $group, 'name' => $case['name'], 'ok' => false, 'error' => $e->getMessage()];
                }
            }
        }
        return $this->summary();
    }

    public function summary(): array
    {
        $total = count($this->results);
        $passed = count(array_filter($this->results, fn ($r) => $r['ok']));
        return ['total' => $total, 'passed' => $passed, 'failed' => $total - $passed,
                'ok' => $passed === $total, 'results' => $this->results];
    }

    /** @return array<string, array{name: string, fn: Closure}> */
    private function cases(callable $groupFn): array
    {
        $out = [];
        $groupFn($out);
        return $out;
    }

    // ------------------------------------------------------------- 14.6

    private function g_profitability(array &$c): void
    {
        $prof = new Profitability($this->db());

        $c['14.6.1'] = ['name' => 'Cost ledger records & summarise by category', 'fn' => function () use ($prof) {
            $ledger = $prof->ledger(1);
            $this->assert(count($ledger) === 5, 'project 1 should have 5 ledger entries, got ' . count($ledger));
            $sum = $prof->ledgerSummary(1);
            $this->assert($this->approx((float) $sum['total_cost'], 12400000), 'ledger total for project 1 should be 12.4M, got ' . $sum['total_cost']);
            $cats = array_column($sum['by_category'], 'category');
            foreach (['labor', 'software', 'subcontractor'] as $expect) {
                $this->assert(in_array($expect, $cats, true), "category $expect missing from ledger summary");
            }
        }];

        $c['14.6.2'] = ['name' => 'Profitability view computed for all projects', 'fn' => function () use ($prof) {
            $rows = $prof->projectProfitability();
            $this->assert(count($rows) === 6, 'six projects expected');
            $byName = array_column($rows, null, 'name');
            $p1 = $byName['Digital Banking Platform Rebuild'];
            // revenue = contract value 18.5M (invoiced 14.04M below contract)
            $this->assert($this->approx((float) $p1['revenue'], 18500000), 'P1 revenue should be contract 18.5M');
            $this->assert($this->approx((float) $p1['margin_pct'], 32.97, 0.2), 'P1 margin ~33%, got ' . $p1['margin_pct']);
            $p5 = $byName['Agro Trade Dashboard'];
            $this->assert($this->approx((float) $p5['revenue'], 4264000), 'P5 revenue = invoiced 4.264M above contract');
        }];

        $c['14.6.3'] = ['name' => 'Profitability KPIs (company margin, best/worst)', 'fn' => function () use ($prof) {
            $k = $prof->kpis();
            $this->assert($k['projects_tracked'] === 6 && $k['active_projects'] === 4, 'project counts wrong');
            $this->assert($this->approx((float) $k['total_cost'], 38870000), 'total cost 38.87M, got ' . $k['total_cost']);
            $revenue = 18500000 + 9500000 + 6800000 + 5200000 + 4264000 + 1800000;
            $this->assert($this->approx((float) $k['total_revenue'], (float) $revenue), 'total revenue mismatch');
            $expectedMargin = ($revenue - 38870000) / $revenue * 100;
            $this->assert($this->approx((float) $k['company_margin_pct'], $expectedMargin, 0.05), 'company margin mismatch: ' . $k['company_margin_pct']);
            $this->assert($k['best_project']['margin_pct'] >= $k['worst_project']['margin_pct'], 'best must beat worst');
        }];

        $c['14.6.4'] = ['name' => 'Low-margin detection (< ' . OPS_LOW_MARGIN_THRESHOLD . '%)', 'fn' => function () use ($prof) {
            $low = $prof->lowMargin();
            $names = array_column($low, 'name');
            sort($names);
            $this->assert($names === ['E-Commerce Store & Payments', 'Logistics Operations Portal', 'Marketing Site Refresh', 'Talent Management SaaS MVP'],
                'low-margin set wrong: ' . implode(', ', $names));
            foreach ($low as $p) {
                $this->assert($p['margin_pct'] < OPS_LOW_MARGIN_THRESHOLD, $p['name'] . ' not actually low margin');
            }
        }];

        $c['14.6.5'] = ['name' => 'Over-budget detection (cost > budget)', 'fn' => function () use ($prof) {
            $over = $prof->overBudget();
            $names = array_column($over, 'name');
            sort($names);
            $this->assert($names === ['E-Commerce Store & Payments', 'Logistics Operations Portal'],
                'over-budget set wrong: ' . implode(', ', $names));
            foreach ($over as $p) {
                $this->assert($p['cost'] > $p['budget'], $p['name'] . ' not actually over budget');
            }
        }];
    }

    // ------------------------------------------------------------- 14.7

    private function g_alerts(array &$c): void
    {
        $alerts = new Alerts($this->db());

        $c['14.7.1'] = ['name' => 'Overdue invoice detection', 'fn' => function () use ($alerts) {
            $d = $alerts->detectOverdueInvoices();
            $this->assert(count($d) === 2, 'two overdue invoices expected, got ' . count($d));
            $nums = array_map(fn ($r) => (str_contains($r['title'], 'INV-2026-003') ? 3 : (str_contains($r['title'], 'INV-2026-006') ? 6 : 0)), $d);
            $this->assert(in_array(3, $nums, true) && in_array(6, $nums, true), 'wrong invoices flagged');
            $crit = array_filter($d, fn ($r) => $r['severity'] === 'critical');
            $this->assert(count($crit) === 1, 'INV-2026-006 (>4M) should be critical');
        }];

        $c['14.7.2'] = ['name' => 'Overdue project detection', 'fn' => function () use ($alerts) {
            $d = $alerts->detectOverdueProjects();
            $this->assert(count($d) === 1 && str_contains($d[0]['title'], 'Logistics'), 'Logistics portal should be the overdue project');
            $this->assert($d[0]['severity'] === 'warning', '5 days overdue => warning');
        }];

        $c['14.7.3'] = ['name' => 'Overdue task detection', 'fn' => function () use ($alerts) {
            $d = $alerts->detectOverdueTasks();
            $this->assert(count($d) === 3, 'three overdue tasks expected, got ' . count($d));
            foreach ($d as $t) {
                $this->assert($t['days'] >= 1, 'task must be at least 1 day overdue');
            }
        }];

        $c['14.7.4'] = ['name' => 'SLA risk detection (breach + at-risk)', 'fn' => function () use ($alerts) {
            $d = $alerts->detectSlaRisk();
            $this->assert(count($d) === 2, 'two SLA-risk tickets expected (1 breach, 1 at-risk), got ' . count($d));
            $breaches = array_filter($d, fn ($r) => str_starts_with($r['title'], 'SLA breached'));
            $risk = array_filter($d, fn ($r) => str_starts_with($r['title'], 'SLA at risk'));
            $this->assert(count($breaches) === 1 && count($risk) === 1, 'need exactly one breach and one at-risk');
            $this->assert(current($breaches)['severity'] === 'critical', 'urgent breach should be critical');
        }];

        $c['14.7.5'] = ['name' => 'Low-margin alerts', 'fn' => function () use ($alerts) {
            $d = array_filter($alerts->detectProfitability(), fn ($r) => $r['type'] === 'low_margin');
            $this->assert(count($d) === 4, 'four low-margin projects expected, got ' . count($d));
        }];

        $c['14.7.6'] = ['name' => 'Over-budget alerts', 'fn' => function () use ($alerts) {
            $d = array_filter($alerts->detectProfitability(), fn ($r) => $r['type'] === 'over_budget');
            $this->assert(count($d) === 2, 'two over-budget projects expected');
        }];

        $c['14.7.7'] = ['name' => 'CRM follow-up alerts', 'fn' => function () use ($alerts) {
            $d = $alerts->detectCrmFollowups();
            $this->assert(count($d) === 2, 'two due follow-ups expected, got ' . count($d));
            $names = array_column($d, 'title');
            $this->assert(count(array_filter($names, fn ($n) => str_contains($n, 'Bisi')) ) === 1, 'Bisi due');
            $this->assert(count(array_filter($names, fn ($n) => str_contains($n, 'Kene')) ) === 1, 'Kene due');
        }];

        $c['14.7.8'] = ['name' => 'Alert prioritization scoring & tiers', 'fn' => function () use ($alerts) {
            $critical = ['severity' => 'critical', 'days' => 20, 'amount' => 9000000];
            $this->assert(Alerts::priorityScore($critical) === 84, '60 base + 20 urgency + 4 value = 84, got ' . Alerts::priorityScore($critical));
            $this->assert(Alerts::priorityTier(84) === 'P1', '84 => P1');
            $extreme = ['severity' => 'critical', 'days' => 30, 'amount' => 50000000];
            $this->assert(Alerts::priorityScore($extreme) === 100, 'max factors cap at 100');
            $warn = ['severity' => 'warning', 'days' => 5, 'amount' => 0];
            $this->assert(Alerts::priorityScore($warn) === 40, 'warning+5d = 40, got ' . Alerts::priorityScore($warn));
            $this->assert(Alerts::priorityTier(40) === 'P3', '40 => P3');
            $info = ['severity' => 'info', 'days' => 0, 'amount' => 0];
            $this->assert(Alerts::priorityScore($info) === 10 && Alerts::priorityTier(10) === 'P4', 'info bare => 10/P4');
            // ordering property
            $this->assert(Alerts::priorityScore($extreme) > Alerts::priorityScore($critical) && Alerts::priorityScore($critical) > Alerts::priorityScore($warn) && Alerts::priorityScore($warn) > Alerts::priorityScore($info), 'monotonic');
        }];

        $c['14.7.9'] = ['name' => 'Alert deduplication on rescan (+ auto-resolve & reopen)', 'fn' => function () use ($alerts) {
            $first = $alerts->scan(false);
            $openAfterFirst = (int) $this->db()->query("SELECT COUNT(*) FROM ops_alerts WHERE status IN ('new','acknowledged')")->fetchColumn();
            $second = $alerts->scan(false);
            $openAfterSecond = (int) $this->db()->query("SELECT COUNT(*) FROM ops_alerts WHERE status IN ('new','acknowledged')")->fetchColumn();
            $this->assert($first['created'] === $openAfterFirst && $openAfterFirst > 0, 'first scan creates alerts');
            $this->assert($second['created'] === 0 && $second['updated'] === $openAfterSecond, 'rescan must dedupe (update), not duplicate');
            $this->assert($openAfterFirst === $openAfterSecond, 'open alert count must not grow on rescan');
            $bumped = (int) $this->db()->query("SELECT MAX(trigger_count) FROM ops_alerts")->fetchColumn();
            $this->assert($bumped >= 2, 'trigger_count should be incremented on re-detection');
            // auto-resolve: pay the overdue invoice, rescan -> its alert resolves
            $this->db()->exec("UPDATE ops_invoices SET status = 'paid', paid_at = '" . Db::now() . "' WHERE invoice_number = 'INV-2026-003'");
            $third = $alerts->scan(false);
            $this->assert($third['auto_resolved'] === 1, 'paid invoice alert must auto-resolve');
            $resolved = $this->db()->query("SELECT COUNT(*) FROM ops_alerts WHERE status = 'resolved'")->fetchColumn();
            $this->assert((int) $resolved === 1, 'exactly one resolved alert expected');
        }];

        $c['14.7.10'] = ['name' => 'Alert automation logging (event log + engine dispatch)', 'fn' => function () use ($alerts) {
            $events = (int) $this->db()->query('SELECT COUNT(*) FROM ops_alert_events')->fetchColumn();
            $this->assert($events > 0, 'alert events must be logged');
            $kinds = $this->db()->query('SELECT DISTINCT event FROM ops_alert_events')->fetchAll(PDO::FETCH_COLUMN);
            foreach (['triggered', 'updated', 'resolved'] as $need) {
                $this->assert(in_array($need, $kinds, true), "event kind $need must be logged");
            }
        }];
    }

    // ------------------------------------------------------------- 14.8

    private function g_reports(array &$c): void
    {
        $reports = new Reports($this->db());

        $c['14.8.1'] = ['name' => 'Daily operations snapshot', 'fn' => function () use ($reports) {
            $p = $reports->generate('daily_operations_snapshot', 'test');
            foreach (['open_alerts', 'critical_alerts', 'overdue_invoices', 'overdue_tasks', 'open_tickets'] as $k) {
                $this->assert(isset($p['metrics'][$k]) && is_numeric($p['metrics'][$k]), "metric $k missing");
            }
            $this->assert($p['metrics']['overdue_invoices'] === 1, 'one overdue invoice after test 14.7.9 paid one');
        }];

        $c['14.8.2'] = ['name' => 'Weekly sales report', 'fn' => function () use ($reports) {
            $p = $reports->generate('weekly_sales', 'test');
            $this->assert(isset($p['metrics']['win_rate_pct']) && $p['metrics']['win_rate_pct'] == 50.0, 'win rate 1 won / 2 decided = 50%');
            $this->assert(!empty($p['tables']['leads_by_stage']), 'stage table required');
        }];

        $c['14.8.3'] = ['name' => 'Revenue report', 'fn' => function () use ($reports) {
            $p = $reports->generate('revenue', 'test');
            $m = $p['metrics'];
            $this->assert($m['total_collected'] > 0 && $m['total_invoiced'] >= $m['total_collected'], 'collected <= invoiced');
            $this->assert($m['outstanding'] > 0, 'seed has outstanding AR');
            $this->assert($m['collection_rate_pct'] <= 100, 'collection rate sane');
        }];

        $c['14.8.4'] = ['name' => 'Project health report', 'fn' => function () use ($reports) {
            $p = $reports->generate('project_health', 'test');
            $rows = $p['tables']['projects'];
            $this->assert($p['metrics']['active_projects'] === 4, 'four active projects');
            $flagged = array_filter($rows, fn ($r) => $r['flags']);
            $this->assert(count($flagged) === 3, 'three of four active projects carry health flags (banking platform is healthy), got ' . count($flagged));
            $overdueProjects = array_filter($rows, fn ($r) => in_array('overdue', $r['flags'], true));
            $this->assert(count($overdueProjects) === 1, 'logistics portal flagged overdue');
        }];

        $c['14.8.5'] = ['name' => 'Profitability report', 'fn' => function () use ($reports) {
            $p = $reports->generate('profitability', 'test');
            $this->assert(count($p['tables']['projects']) === 6, 'all projects in report');
            $this->assert($p['metrics']['low_margin_count'] === 4 && $p['metrics']['over_budget_count'] === 2, 'detection counts embedded');
        }];

        $c['14.8.6'] = ['name' => 'Team performance report', 'fn' => function () use ($reports) {
            $p = $reports->generate('team_performance', 'test');
            $this->assert(count($p['tables']['team']) === 5, 'five team members');
            $done = array_column($p['tables']['team'], 'tasks_done', 'member');
            $this->assert((int) $done['Ngozi Eze'] === 3, 'Ngozi completed 3 tasks');
        }];

        $c['14.8.7'] = ['name' => 'Support / SLA report', 'fn' => function () use ($reports) {
            $p = $reports->generate('support_sla', 'test');
            $m = $p['metrics'];
            $this->assert($m['total_tickets'] === 7, 'seven tickets seeded');
            $this->assert($m['open_tickets'] === 4, 'four unresolved');
            $this->assert($m['avg_first_response_hours'] > 0, 'response time computed');
        }];

        $c['14.8.8'] = ['name' => 'Monthly executive report (composite)', 'fn' => function () use ($reports) {
            $p = $reports->generate('monthly_executive', 'test');
            foreach (['profitability', 'revenue', 'sales', 'support', 'alerts'] as $section) {
                $this->assert(isset($p['metrics'][$section]), "exec section $section missing");
            }
        }];

        $c['14.8.9'] = ['name' => 'Historical snapshots retained', 'fn' => function () use ($reports) {
            $reports->generate('revenue', 'test'); // second copy of same type
            $snaps = $reports->snapshots('revenue');
            $this->assert(count($snaps) >= 2, 'snapshots must accumulate, got ' . count($snaps));
            foreach ($snaps as $s) {
                $this->assert((int) $s['validation_ok'] === 1 && $s['status'] === 'final', 'stored snapshots validated+final');
            }
        }];

        $c['14.8.10'] = ['name' => 'Report validation rejects malformed payloads', 'fn' => function () use ($reports) {
            $good = $reports->generate('daily_operations_snapshot', 'test');
            $this->assert($good['meta']['validation']['ok'], 'well-formed report validates');
            $bad1 = $good;
            unset($bad1['metrics']['open_alerts']);
            $v = $reports->validate('daily_operations_snapshot', $bad1);
            $this->assert(!$v['ok'], 'missing metric must fail');
            $bad2 = $good;
            $bad2['metrics']['overdue_tasks'] = -5;
            $this->assert(!$reports->validate('daily_operations_snapshot', $bad2)['ok'], 'negative count must fail');
            $bad3 = ['meta' => ['type' => 'revenue', 'row_count' => 1], 'metrics' => ['total_invoiced' => 1, 'total_collected' => 2, 'collection_rate_pct' => 200]];
            $this->assert(!$reports->validate('revenue', $bad3)['ok'], 'collection rate >100% must fail');
            $bad4 = $good;
            $bad4['meta']['type'] = 'something_else';
            $this->assert(!$reports->validate('daily_operations_snapshot', $bad4)['ok'], 'type mismatch must fail');
        }];
    }

    // ------------------------------------------------------------- 14.9

    private function g_automation(array &$c): void
    {
        $engine = new Automation($this->db());

        $c['14.9.1'] = ['name' => 'Automation rules exist & CRUD', 'fn' => function () use ($engine) {
            $rules = $engine->rules();
            $this->assert(count($rules) === 6, 'six seeded rules expected, got ' . count($rules));
            $id = $engine->create('Test rule', 'manual', null, 'log_notification', ['template' => 'hi'], 'event', 30);
            $this->assert($engine->rule($id)['name'] === 'Test rule', 'created rule retrievable');
            $this->assert($engine->set_active($id, false), 'rule can be disabled');
        }];

        $c['14.9.2'] = ['name' => 'Rule condition evaluation', 'fn' => function () {
            $this->assert(Automation::conditionsMatch(null, ['severity' => 'info']), 'no conditions = match');
            $this->assert(Automation::conditionsMatch('{"severity":"critical"}', ['severity' => 'critical']), 'exact match');
            $this->assert(!Automation::conditionsMatch('{"severity":"critical"}', ['severity' => 'warning']), 'mismatch must not match');
            $this->assert(Automation::conditionsMatch('{"severity":"critical","day_of_week":"Monday"}', ['severity' => 'critical']), 'schedule meta ignored in context matching');
            $this->assert(Automation::conditionsMatch('{"type":["overdue_invoice","sla_risk"]}', ['type' => 'sla_risk']), 'array membership');
        }];

        $c['14.9.3'] = ['name' => 'Trigger processing (event + schedule)', 'fn' => function () use ($engine) {
            // event trigger: critical alert raised -> escalation rule (log_notification)
            $res = Automation::fireEvent($this->db(), 'alert_raised', ['severity' => 'critical', 'title' => 'Test outage', 'alert_id' => 999999, 'type' => 'custom']);
            $this->assert(count($res) === 1 && $res[0]['status'] === 'success', 'escalation rule should fire once');
            $matched = Automation::fireEvent($this->db(), 'alert_raised', ['severity' => 'info', 'title' => 'low', 'alert_id' => 888888, 'type' => 'custom']);
            $this->assert(count($matched) === 0, 'non-critical must not trigger escalation');
            // schedule due processing
            $ran = $engine->runDueRules();
            $names = array_column($ran, 'name');
            $this->assert(in_array('Daily risk scan', $names, true), 'daily risk scan should run when due');
        }];

        $c['14.9.4'] = ['name' => 'Action execution (all four actions)', 'fn' => function () use ($engine) {
            // alert_scan action (fresh adhoc rule, dedup 0 — seeded rule already ran in 14.9.3)
            $scanRuleId = $engine->create('Adhoc scan', 'manual', null, 'alert_scan', null, 'event', 0);
            $this->assert($engine->run($engine->rule($scanRuleId), 'manual') === 'success', 'alert_scan action');
            // generate_report action (fresh adhoc rule)
            $reportRuleId = $engine->create('Adhoc report', 'manual', null, 'generate_report', ['report_type' => 'revenue'], 'event', 0);
            $this->assert($engine->run($engine->rule($reportRuleId), 'manual') === 'success', 'generate_report action');
            $snap = $this->db()->query("SELECT id FROM ops_report_snapshots WHERE generated_by LIKE 'automation:%'")->fetchColumn();
            $this->assert((int) $snap > 0, 'automation-generated snapshot exists');
            // log_notification action with interpolation
            $noteRule = $engine->create('Interp', 'alert_raised', null, 'log_notification', ['template' => 'ALERT: {title}'], 'event', 0);
            Automation::fireEvent($this->db(), 'alert_raised', ['severity' => 'warning', 'title' => 'Webhook down', 'alert_id' => 777777, 'type' => 'custom', 'entity_type' => null, 'entity_id' => null]);
            $hit = false;
            foreach ($this->db()->query("SELECT details FROM ops_alert_events WHERE event = 'notification'")->fetchAll(PDO::FETCH_COLUMN) as $d) {
                if (str_contains((string) $d, 'ALERT: Webhook down')) { $hit = true; }
            }
            $this->assert($hit, 'log_notification must interpolate {title}');
            // raise_alert action
            $raiseRule = $engine->create('Auto flag', 'manual', null, 'raise_alert', ['title' => 'Auto flag fired', 'severity' => 'warning'], 'event', 0);
            $this->assert($engine->run($engine->rule($raiseRule), 'manual') === 'success', 'raise_alert action');
            $auto = $this->db()->query("SELECT COUNT(*) FROM ops_alerts WHERE alert_key LIKE 'automation:%'")->fetchColumn();
            $this->assert((int) $auto >= 1, 'automation-raised alert stored');
        }];

        $c['14.9.5'] = ['name' => 'Duplicate prevention window', 'fn' => function () use ($engine) {
            $ruleId = $engine->create('Dedup probe', 'alert_raised', null, 'log_notification', ['template' => 'dup {title}'], 'event', 60);
            $rule = $engine->rule($ruleId);
            $ctx = ['severity' => 'critical', 'title' => 'Same incident', 'alert_id' => 424242, 'type' => 'custom'];
            $first = $engine->run($rule, 'alert_raised', $ctx);
            $second = $engine->run($rule, 'alert_raised', $ctx);
            $this->assert($first === 'success', 'first execution succeeds');
            $this->assert($second === 'duplicate_blocked', 'second identical execution blocked, got ' . $second);
            $blocked = $this->db()->query("SELECT COUNT(*) FROM ops_automation_runs WHERE status = 'duplicate_blocked'")->fetchColumn();
            $this->assert((int) $blocked >= 1, 'blocked runs recorded in history');
        }];

        $c['14.9.6'] = ['name' => 'Execution history with outcomes', 'fn' => function () {
            $runs = $this->db()->query('SELECT * FROM ops_automation_runs ORDER BY id DESC LIMIT 5')->fetchAll();
            $this->assert(count($runs) === 5, 'history populated');
            foreach ($runs as $r) {
                $this->assert($r['fingerprint'] !== '' && $r['started_at'] !== null, 'runs carry fingerprint + timing');
                if ($r['status'] === 'success') {
                    $this->assert(!empty($r['outcome_json']), 'success runs store outcome json');
                }
            }
        }];

        $c['14.9.7'] = ['name' => 'Failure logging', 'fn' => function () use ($engine) {
            $ruleId = $engine->create('Broken report', 'manual', null, 'generate_report', ['report_type' => 'does_not_exist'], 'event', 0);
            $rule = $engine->rule($ruleId);
            $status = $engine->run($rule, 'manual');
            $this->assert($status === 'failed', 'bad report type must fail, got ' . $status);
            $row = $this->db()->query("SELECT * FROM ops_automation_runs WHERE rule_id = $ruleId AND status = 'failed'")->fetch();
            $this->assert($row && str_contains((string) $row['error_message'], 'Unknown report type'), 'error message logged');
            $this->assert((int) $engine->rule($ruleId)['fail_count'] === 1, 'fail_count incremented');
        }];

        $c['14.9.8'] = ['name' => 'Scheduled execution (daily/weekly/monthly due logic)', 'fn' => function () use ($engine) {
            $daily = array_values(array_filter($engine->rules(), fn ($r) => $r['schedule'] === 'daily'))[0];
            $this->assert($engine->isDue($daily, date('Y-m-d H:i:s', time() + 86400)), 'daily rule due tomorrow');
            $this->assert(!$engine->isDue($daily, Db::now()), 'daily rule already ran today');
            $weekly = array_values(array_filter($engine->rules(), fn ($r) => $r['schedule'] === 'weekly'))[0];
            $monday = date('Y-m-d 09:00:00', strtotime('monday this week'));
            $tuesday = date('Y-m-d 09:00:00', strtotime('tuesday this week'));
            $this->assert($engine->isDue($weekly, $monday), 'weekly rule due on its Monday');
            $this->assert(!$engine->isDue($weekly, $tuesday), 'weekly rule not due on Tuesday');
            $monthly = array_values(array_filter($engine->rules(), fn ($r) => $r['schedule'] === 'monthly'))[0];
            $firstNext = date('Y-m-01 09:00:00', strtotime('+1 month'));
            $secondNext = date('Y-m-02 09:00:00', strtotime('+1 month'));
            $this->assert($engine->isDue($monthly, $firstNext), 'monthly rule due on the 1st');
            $this->assert(!$engine->isDue($monthly, $secondNext), 'monthly rule not due on the 2nd');
        }];

        $c['14.9.9'] = ['name' => 'Automation monitoring metrics', 'fn' => function () use ($engine) {
            $m = $engine->monitoring();
            $this->assert(count($m) > 0, 'monitoring covers rules');
            $broken = array_values(array_filter($m, fn ($x) => $x['rule']['name'] === 'Broken report'))[0];
            $this->assert($broken['failed'] === 1, 'monitoring reports the failure');
            $this->assert($broken['success_rate_pct'] === 0.0, 'success rate computed');
        }];
    }

    // ------------------------------------------------------------- 14.10

    private function g_bi(array &$c): void
    {
        $an = new Analytics($this->db());

        $c['14.10.1'] = ['name' => 'Executive KPI dashboard data', 'fn' => function () use ($an) {
            $k = $an->execKpis();
            foreach (['revenue_mtd_invoiced', 'outstanding_total', 'active_projects', 'pipeline_value', 'alerts', 'profitability'] as $key) {
                $this->assert(array_key_exists($key, $k), "KPI $key missing");
            }
            $this->assert($k['active_projects'] === 4 && $k['overdue_projects'] === 1, 'project KPIs correct');
            $this->assert($k['profitability']['over_budget_count'] === 2, 'profitability KPIs embedded');
        }];

        $c['14.10.2'] = ['name' => 'Sales analytics', 'fn' => function () use ($an) {
            $s = $an->sales();
            $this->assert($s['win_rate_pct'] == 50.0, 'win rate 50%');
            $this->assert($s['pipeline_value'] > 0, 'pipeline valued');
            $stages = array_column($s['by_stage'], 'stage');
            $this->assert(in_array('proposal', $stages, true), 'stage breakdown present');
        }];

        $c['14.10.3'] = ['name' => 'Finance analytics (AR aging)', 'fn' => function () use ($an) {
            $f = $an->finance();
            $agingSum = array_sum($f['aging']);
            $outstanding = (float) $this->db()->query("SELECT COALESCE(SUM(total),0) FROM ops_invoices WHERE status = 'sent'")->fetchColumn();
            $this->assert($this->approx($agingSum, $outstanding, 1.0), 'aging buckets must sum to outstanding AR');
            $this->assert($f['aging']['1_30'] > 0, 'seeded overdue AR lands in 1-30 bucket');
            $this->assert(count($f['monthly']) >= 3, 'monthly series present');
        }];

        $c['14.10.4'] = ['name' => 'Project analytics', 'fn' => function () use ($an) {
            $p = $an->projects();
            $statuses = array_column($p['by_status'], 'status');
            $this->assert(in_array('active', $statuses, true) && in_array('completed', $statuses, true), 'status distribution');
            $this->assert(count($p['budget_vs_cost']) === 6, 'budget-vs-cost for all projects');
        }];

        $c['14.10.5'] = ['name' => 'Team analytics', 'fn' => function () use ($an) {
            $t = $an->team();
            $this->assert(count($t) === 5, 'all members');
            $saleem = array_values(array_filter($t, fn ($m) => $m['name'] === 'Saleem Shaik'))[0];
            $this->assert((int) $saleem['done'] === 2, 'Saleem done tasks');
            $this->assert((int) $saleem['overdue'] === 1, 'Saleem has one overdue task');
        }];

        $c['14.10.6'] = ['name' => 'Support analytics', 'fn' => function () use ($an) {
            $s = $an->support();
            $this->assert(count($s['open_tickets']) === 4, 'four open/pending tickets');
            $this->assert($s['resolved_count'] === 3, 'three resolved');
        }];

        $c['14.10.7'] = ['name' => 'Profitability analytics ranking data', 'fn' => function () use ($an) {
            $rows = $an->profitability();
            $margins = array_column($rows, 'margin_pct');
            $this->assert(count($margins) === 6, 'margins for all projects');
            $this->assert(max($margins) > 30 && min($margins) < 0, 'seeded spread (best >30%, worst negative)');
        }];

        $c['14.10.8'] = ['name' => 'Predictive alerts dashboard data', 'fn' => function () use ($an) {
            $v = $an->alertsView();
            $this->assert(count($v['open']) > 0, 'open alerts exposed');
            $this->assert(!empty($v['trend_7d']), '7-day trend computed');
        }];

        $c['14.10.9'] = ['name' => 'Reports integration (latest per type)', 'fn' => function () use ($an) {
            $latest = $an->reportsIntegration();
            $this->assert(count($latest) >= 7, 'latest snapshots available for dashboard, got ' . count($latest));
        }];

        $c['14.10.10'] = ['name' => 'Final production regression sweep', 'fn' => function () {
            // Full-stack smoke: alert scan -> automation -> all 8 reports -> validation
            $alerts = new Alerts($this->db());
            $scan = $alerts->scan();
            $this->assert($scan['candidates'] > 0, 'scan finds conditions');
            $engine = new Automation($this->db());
            $ran = $engine->runDueRules();
            $this->assert(is_array($ran), 'scheduler sweep runs');
            $reports = new Reports($this->db());
            foreach (array_keys(Reports::TYPES) as $type) {
                $p = $reports->generate($type, 'regression');
                $this->assert($p['meta']['validation']['ok'], "regression report $type must validate: " . implode('; ', $p['meta']['validation']['errors']));
            }
            $bad = (int) $this->db()->query("SELECT COUNT(*) FROM ops_report_snapshots WHERE validation_ok = 0")->fetchColumn();
            $this->assert($bad === 0, 'no invalid snapshots may be finalized');
            $failedRuns = (int) $this->db()->query("SELECT COUNT(*) FROM ops_automation_runs WHERE status = 'failed'")->fetchColumn();
            $this->assert($failedRuns === 1, 'only the intentional failure-test run may fail, got ' . $failedRuns);
        }];
    }
}

// ---- CLI runner ----
if (PHP_SAPI === 'cli' || defined('OPS_TESTDRIVER')) {
    $runner = new OpsTestRunner();
    $summary = $runner->run();
    $lastGroup = null;
    foreach ($summary['results'] as $r) {
        if ($r['group'] !== $lastGroup) {
            echo "\n== {$r['group']} ==\n";
            $lastGroup = $r['group'];
        }
        echo $r['ok'] ? "  [PASS] {$r['id']}  {$r['name']}\n" : "  [FAIL] {$r['id']}  {$r['name']}\n          {$r['error']}\n";
    }
    echo "\n{$summary['passed']}/{$summary['total']} passed, {$summary['failed']} failed\n";
    if (PHP_SAPI === 'cli') {
        exit($summary['ok'] ? 0 : 1);
    }
}
