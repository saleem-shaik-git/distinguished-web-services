<?php
// 14.7 — Predictive Risk & Alerts.
//   14.7.1 Overdue invoice detection     detectOverdueInvoices()
//   14.7.2 Overdue project detection     detectOverdueProjects()
//   14.7.3 Overdue task detection        detectOverdueTasks()
//   14.7.4 SLA risk detection            detectSlaRisk()
//   14.7.5 Low-margin alerts             via Profitability::lowMargin()
//   14.7.6 Over-budget alerts            via Profitability::overBudget()
//   14.7.7 CRM follow-up alerts          detectCrmFollowups()
//   14.7.8 Alert prioritization          priorityScore()
//   14.7.9 Alert deduplication           scan() (alert_key + trigger_count)
//   14.7.10 Automation logging/testing   event log rows + covered by tests
declare(strict_types=1);

final class Alerts
{
    public const TYPES = ['overdue_invoice', 'overdue_project', 'overdue_task', 'sla_risk', 'low_margin', 'over_budget', 'crm_followup'];

    public function __construct(private PDO $db) {}

    // ------------------------------------------------------------------
    // Detectors — each returns candidate alerts: key, severity, score
    // inputs, entity binding, title, message.
    // ------------------------------------------------------------------

    /** 14.7.1 — unpaid, past-due invoices. */
    public function detectOverdueInvoices(): array
    {
        $rows = $this->db->query(
            "SELECT i.id, i.invoice_number, i.total, i.due_date, c.name AS client
             FROM ops_invoices i JOIN ops_clients c ON c.id = i.client_id
             WHERE i.status = 'sent' AND i.due_date < '" . Db::today() . "'"
        )->fetchAll();
        // day diff computed in PHP for driver portability
        $out = [];
        foreach ($rows as $r) {
            $days = (int) floor((strtotime(Db::today()) - strtotime($r['due_date'])) / 86400);
            $out[] = [
                'alert_key' => 'overdue_invoice:' . $r['id'],
                'type' => 'overdue_invoice',
                'severity' => $days > 30 || (float) $r['total'] > 4000000 ? 'critical' : 'warning',
                'entity_type' => 'invoice', 'entity_id' => (int) $r['id'],
                'days' => $days, 'amount' => (float) $r['total'],
                'title' => 'Overdue invoice ' . $r['invoice_number'],
                'message' => sprintf('%s is %d days overdue (%s). Client: %s.', $r['invoice_number'], $days, ops_money($r['total']), $r['client']),
            ];
        }
        return $out;
    }

    /** 14.7.2 — active projects past their due date. */
    public function detectOverdueProjects(): array
    {
        $rows = $this->db->query(
            "SELECT p.id, p.name, p.due_date, c.name AS client FROM ops_projects p
             LEFT JOIN ops_clients c ON c.id = p.client_id
             WHERE p.status IN ('active','on_hold') AND p.due_date < '" . Db::today() . "'"
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $days = (int) floor((strtotime(Db::today()) - strtotime($r['due_date'])) / 86400);
            $out[] = [
                'alert_key' => 'overdue_project:' . $r['id'],
                'type' => 'overdue_project',
                'severity' => $days > 14 ? 'critical' : 'warning',
                'entity_type' => 'project', 'entity_id' => (int) $r['id'],
                'days' => $days, 'amount' => 0.0,
                'title' => 'Project overdue: ' . $r['name'],
                'message' => sprintf('"%s" for %s passed its due date %d days ago.', $r['name'], $r['client'] ?? 'client', $days),
            ];
        }
        return $out;
    }

    /** 14.7.3 — open tasks past their due date. */
    public function detectOverdueTasks(): array
    {
        $rows = $this->db->query(
            "SELECT t.id, t.title, t.due_date, t.status, p.name AS project, m.name AS assignee
             FROM ops_tasks t JOIN ops_projects p ON p.id = t.project_id
             LEFT JOIN ops_team m ON m.id = t.team_id
             WHERE t.status NOT IN ('done') AND t.due_date < '" . Db::today() . "'"
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $days = (int) floor((strtotime(Db::today()) - strtotime($r['due_date'])) / 86400);
            $out[] = [
                'alert_key' => 'overdue_task:' . $r['id'],
                'type' => 'overdue_task',
                'severity' => $days > 7 ? 'warning' : 'info',
                'entity_type' => 'task', 'entity_id' => (int) $r['id'],
                'days' => $days, 'amount' => 0.0,
                'title' => 'Task overdue: ' . $r['title'],
                'message' => sprintf('"%s" (%s) on "%s" is %d days overdue. Assignee: %s.', $r['title'], $r['status'], $r['project'], $days, $r['assignee'] ?? 'unassigned'),
            ];
        }
        return $out;
    }

    /** 14.7.4 — SLA risk: breached or at-risk (due within 3h) unresolved tickets. */
    public function detectSlaRisk(): array
    {
        $rows = $this->db->query(
            "SELECT t.id, t.subject, t.priority, t.status, t.sla_due_at, t.first_response_at, c.name AS client
             FROM ops_tickets t JOIN ops_clients c ON c.id = t.client_id
             WHERE t.status IN ('open','pending')"
        )->fetchAll();
        $now = time();
        $out = [];
        foreach ($rows as $r) {
            if ($r['first_response_at'] !== null) {
                continue; // already responded — SLA (first response) satisfied
            }
            $due = strtotime($r['sla_due_at']);
            $hoursLeft = ($due - $now) / 3600;
            if ($hoursLeft > 3) {
                continue; // healthy
            }
            $breached = $hoursLeft <= 0;
            $out[] = [
                'alert_key' => 'sla_risk:' . $r['id'],
                'type' => 'sla_risk',
                'severity' => $breached && $r['priority'] === 'urgent' ? 'critical' : ($breached ? 'warning' : 'info'),
                'entity_type' => 'ticket', 'entity_id' => (int) $r['id'],
                'days' => 0, 'amount' => 0.0, 'hours_left' => round($hoursLeft, 1),
                'title' => ($breached ? 'SLA breached: ' : 'SLA at risk: ') . $r['subject'],
                'message' => sprintf('%s ticket from %s is %s (SLA due %s).', ucfirst($r['priority']), $r['client'], $breached ? 'past its SLA deadline' : 'within 3 hours of its SLA deadline', $r['sla_due_at']),
            ];
        }
        return $out;
    }

    /** 14.7.5/14.7.6 — low-margin & over-budget project alerts. */
    public function detectProfitability(): array
    {
        $prof = new Profitability($this->db);
        $out = [];
        foreach ($prof->lowMargin() as $p) {
            $out[] = [
                'alert_key' => 'low_margin:' . $p['id'],
                'type' => 'low_margin',
                'severity' => $p['margin_pct'] < 0 ? 'critical' : 'warning',
                'entity_type' => 'project', 'entity_id' => $p['id'],
                'days' => 0, 'amount' => abs($p['gross_profit']),
                'margin_pct' => $p['margin_pct'],
                'title' => 'Low margin: ' . $p['name'],
                'message' => sprintf('"%s" margin is %s (cost %s vs revenue %s) — below the %s%% threshold.', $p['name'], ops_pct($p['margin_pct']), ops_money($p['cost']), ops_money($p['revenue']), OPS_LOW_MARGIN_THRESHOLD),
            ];
        }
        foreach ($prof->overBudget() as $p) {
            $out[] = [
                'alert_key' => 'over_budget:' . $p['id'],
                'type' => 'over_budget',
                'severity' => $p['budget_use_pct'] > 115 ? 'critical' : 'warning',
                'entity_type' => 'project', 'entity_id' => $p['id'],
                'days' => 0, 'amount' => $p['cost'] - $p['budget'],
                'budget_use_pct' => $p['budget_use_pct'],
                'title' => 'Over budget: ' . $p['name'],
                'message' => sprintf('"%s" has used %s of its %s budget (cost %s).', $p['name'], ops_pct($p['budget_use_pct']), ops_money($p['budget']), ops_money($p['cost'])),
            ];
        }
        return $out;
    }

    /** 14.7.7 — CRM follow-ups that are due (or overdue) on active leads. */
    public function detectCrmFollowups(): array
    {
        $rows = $this->db->query(
            "SELECT id, name, company, stage, value_estimate, next_followup_at FROM ops_leads
             WHERE stage NOT IN ('won','lost') AND next_followup_at IS NOT NULL AND next_followup_at <= '" . Db::now() . "'"
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $hours = (time() - strtotime($r['next_followup_at'])) / 3600;
            $out[] = [
                'alert_key' => 'crm_followup:' . $r['id'],
                'type' => 'crm_followup',
                'severity' => $hours > 48 ? 'warning' : 'info',
                'entity_type' => 'lead', 'entity_id' => (int) $r['id'],
                'days' => (int) floor($hours / 24), 'amount' => (float) $r['value_estimate'],
                'title' => 'Follow up: ' . $r['name'],
                'message' => sprintf('Lead "%s" (%s, stage: %s, est. %s) is due for follow-up.', $r['name'], $r['company'] ?? '—', $r['stage'], ops_money($r['value_estimate'])),
            ];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // 14.7.8 — prioritization: score 0-100 from severity, urgency & value.
    // ------------------------------------------------------------------
    public static function priorityScore(array $candidate): int
    {
        $base = ['critical' => 60, 'warning' => 30, 'info' => 10][$candidate['severity']] ?? 10;
        $urgency = min(20, max(0, ($candidate['days'] ?? 0)) * 2);                     // up to +20 by overdue days
        $value = min(20, (int) floor(($candidate['amount'] ?? 0) / 2000000));          // up to +20 by naira impact (₦2M/pt)
        return (int) min(100, $base + $urgency + $value);
    }

    public static function priorityTier(int $score): string
    {
        return $score >= 75 ? 'P1' : ($score >= 50 ? 'P2' : ($score >= 25 ? 'P3' : 'P4'));
    }

    // ------------------------------------------------------------------
    // 14.7.9 — dedup + persistence. Returns stats. Re-running the scan
    // updates existing open alerts (trigger_count++, last_triggered_at)
    // instead of duplicating rows.
    // ------------------------------------------------------------------
    public function scan(bool $fireAutomation = true): array
    {
        $candidates = array_merge(
            $this->detectOverdueInvoices(),
            $this->detectOverdueProjects(),
            $this->detectOverdueTasks(),
            $this->detectSlaRisk(),
            $this->detectProfitability(),
            $this->detectCrmFollowups()
        );

        $stats = ['created' => 0, 'updated' => 0, 'candidates' => count($candidates), 'by_type' => []];
        $newAlerts = [];
        $now = Db::now();

        foreach ($candidates as $c) {
            $stats['by_type'][$c['type']] = ($stats['by_type'][$c['type']] ?? 0) + 1;
            $score = self::priorityScore($c);
            $existing = $this->findByKey($c['alert_key']);
            if ($existing && $existing['status'] !== 'resolved') {
                // dedup: same open alert -> bump counters instead of a duplicate row
                $upd = $this->db->prepare(
                    'UPDATE ops_alerts SET last_triggered_at = ?, trigger_count = trigger_count + 1,
                     priority_score = MAX(priority_score, ?), severity = ?, message = ? WHERE id = ?'
                );
                $upd->execute([$now, $score, $c['severity'], $c['message'], $existing['id']]);
                $this->logEvent((int) $existing['id'], 'updated', 're-detected, trigger count incremented');
                $stats['updated']++;
            } elseif ($existing && $existing['status'] === 'resolved') {
                // condition returned after resolution -> reopen as a fresh alert row
                $newAlerts[] = $this->create($c, $score, $now);
                $stats['created']++;
            } else {
                $newAlerts[] = $this->create($c, $score, $now);
                $stats['created']++;
            }
        }

        // auto-resolve alerts whose condition cleared
        $stats['auto_resolved'] = $this->autoResolve(array_column($candidates, 'alert_key'), $now);

        // 14.7.10 — automation logging: fire alert_raised events for new alerts
        if ($fireAutomation && class_exists('Automation')) {
            foreach ($newAlerts as $a) {
                try {
                    Automation::fireEvent($this->db, 'alert_raised', [
                        'alert_id' => $a['id'], 'type' => $a['type'], 'severity' => $a['severity'],
                        'title' => $a['title'], 'entity_type' => $a['entity_type'], 'entity_id' => $a['entity_id'],
                    ]);
                } catch (Throwable $e) {
                    error_log('[ops] automation dispatch failed: ' . $e->getMessage());
                }
            }
        }
        return $stats;
    }

    private function create(array $c, int $score, string $now): array
    {
        $ins = $this->db->prepare(
            'INSERT INTO ops_alerts (alert_key, type, severity, priority_score, entity_type, entity_id, title, message, status, trigger_count, first_triggered_at, last_triggered_at)
             VALUES (?,?,?,?,?,?,?,?,?,1,?,?)'
        );
        $ins->execute([
            $c['alert_key'], $c['type'], $c['severity'], $score,
            $c['entity_type'] ?? null, $c['entity_id'] ?? null, $c['title'], $c['message'], 'new', $now, $now,
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->logEvent($id, 'triggered', 'severity=' . $c['severity'] . ' score=' . $score);
        return ['id' => $id, 'type' => $c['type'], 'severity' => $c['severity'], 'title' => $c['title'],
                'entity_type' => $c['entity_type'] ?? null, 'entity_id' => $c['entity_id'] ?? null];
    }

    private function findByKey(string $key): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ops_alerts WHERE alert_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Close open alerts whose detection key no longer appears. */
    private function autoResolve(array $activeKeys, string $now): int
    {
        if (!$activeKeys) {
            return 0;
        }
        $open = $this->db->query("SELECT id, alert_key FROM ops_alerts WHERE status IN ('new','acknowledged')")->fetchAll();
        $alive = array_flip($activeKeys);
        $n = 0;
        $upd = $this->db->prepare("UPDATE ops_alerts SET status = 'resolved', resolved_at = ? WHERE id = ?");
        foreach ($open as $a) {
            if (!isset($alive[$a['alert_key']])) {
                $upd->execute([$now, $a['id']]);
                $this->logEvent((int) $a['id'], 'resolved', 'auto-resolved: condition cleared');
                $n++;
            }
        }
        return $n;
    }

    public function logEvent(int $alertId, string $event, string $details): void
    {
        $ins = $this->db->prepare('INSERT INTO ops_alert_events (alert_id, event, details) VALUES (?,?,?)');
        $ins->execute([$alertId, $event, mb_substr($details, 0, 490)]);
    }

    public function acknowledge(int $id): bool
    {
        $upd = $this->db->prepare("UPDATE ops_alerts SET status = 'acknowledged' WHERE id = ? AND status = 'new'");
        $upd->execute([$id]);
        if ($upd->rowCount()) {
            $this->logEvent($id, 'acknowledged', 'acknowledged via admin');
            return true;
        }
        return false;
    }

    public function resolve(int $id): bool
    {
        $upd = $this->db->prepare("UPDATE ops_alerts SET status = 'resolved', resolved_at = ? WHERE id = ? AND status != 'resolved'");
        $upd->execute([Db::now(), $id]);
        if ($upd->rowCount()) {
            $this->logEvent($id, 'resolved', 'resolved via admin');
            return true;
        }
        return false;
    }

    public function open(int $limit = 200): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM ops_alerts WHERE status IN ('new','acknowledged') ORDER BY priority_score DESC, last_triggered_at DESC LIMIT " . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countsBySeverity(): array
    {
        return [
            'critical' => (int) $this->db->query("SELECT COUNT(*) FROM ops_alerts WHERE status IN ('new','acknowledged') AND severity = 'critical'")->fetchColumn(),
            'warning' => (int) $this->db->query("SELECT COUNT(*) FROM ops_alerts WHERE status IN ('new','acknowledged') AND severity = 'warning'")->fetchColumn(),
            'info' => (int) $this->db->query("SELECT COUNT(*) FROM ops_alerts WHERE status IN ('new','acknowledged') AND severity = 'info'")->fetchColumn(),
        ];
    }

    public function events(int $alertId): array
    {
        $stmt = $this->db->prepare('SELECT event, details, created_at FROM ops_alert_events WHERE alert_id = ? ORDER BY id DESC LIMIT 50');
        $stmt->execute([$alertId]);
        return $stmt->fetchAll();
    }
}
