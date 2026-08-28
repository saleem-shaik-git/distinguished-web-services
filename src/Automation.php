<?php
// 14.9 — Automation Engine.
//   14.9.1 Automation rules        rules()/rule()
//   14.9.2 Rule evaluation         conditionsMatch()
//   14.9.3 Trigger processing      fireEvent() / runDueRules()
//   14.9.4 Action execution        executeAction()
//   14.9.5 Duplicate prevention    fingerprint window (dedup_minutes)
//   14.9.6 Execution history       ops_automation_runs
//   14.9.7 Failure logging         status='failed' + error_message
//   14.9.8 Scheduled execution     runDueRules() (via cron.php or admin)
//   14.9.9 Automation monitoring   monitoring()
//   14.9.10 Production testing     covered by tests/run.php + admin selftest
declare(strict_types=1);

final class Automation
{
    public const ACTIONS = ['alert_scan', 'generate_report', 'log_notification', 'raise_alert'];
    public const TRIGGERS = ['daily_check', 'alert_raised', 'manual'];

    public function __construct(private PDO $db) {}

    // ------------------------------------------------- 14.9.1 rules

    public function rules(bool $includeInactive = true): array
    {
        $sql = 'SELECT * FROM ops_automation_rules' . ($includeInactive ? '' : ' WHERE is_active = 1') . ' ORDER BY id';
        return $this->db->query($sql)->fetchAll();
    }

    public function rule(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ops_automation_rules WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function set_active(int $id, bool $active): bool
    {
        $stmt = $this->db->prepare('UPDATE ops_automation_rules SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
        return (bool) $stmt->rowCount();
    }

    public function create(string $name, string $trigger, ?array $conditions, string $action, ?array $config, string $schedule = 'daily', int $dedupMinutes = 60): int
    {
        if (!in_array($trigger, self::TRIGGERS, true) || !in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException('Invalid trigger or action');
        }
        $ins = $this->db->prepare(
            'INSERT INTO ops_automation_rules (name, trigger_type, conditions_json, action_type, action_config_json, schedule, is_active, dedup_minutes)
             VALUES (?,?,?,?,?,?,1,?)'
        );
        $ins->execute([
            $name, $trigger,
            $conditions ? json_encode($conditions) : null,
            $action, $config ? json_encode($config) : null,
            $schedule, $dedupMinutes,
        ]);
        return (int) $this->db->lastInsertId();
    }

    // ------------------------------------------------- 14.9.2 evaluation

    /** Match a rule's JSON conditions object against an event context. */
    public static function conditionsMatch(?string $conditionsJson, array $context): bool
    {
        if (!$conditionsJson) {
            return true;
        }
        $conds = json_decode($conditionsJson, true);
        if (!is_array($conds)) {
            return true; // treat malformed conditions as always-match (logged elsewhere)
        }
        // scheduling meta-conditions are environment, not context
        unset($conds['day_of_week'], $conds['day_of_month']);
        foreach ($conds as $key => $expected) {
            $actual = $context[$key] ?? null;
            if (is_array($expected)) {
                if (!in_array($actual, $expected, true)) {
                    return false;
                }
            } elseif ((string) $actual !== (string) $expected) {
                return false;
            }
        }
        return true;
    }

    /** Is a scheduled rule due to run right now? (daily/weekly/monthly with last_run_at) */
    public function isDue(array $rule, string $now): bool
    {
        if ((int) $rule['is_active'] !== 1) {
            return false;
        }
        $conds = $rule['conditions_json'] ? json_decode($rule['conditions_json'], true) : [];
        $conds = is_array($conds) ? $conds : [];
        $last = $rule['last_run_at'] ? strtotime($rule['last_run_at']) : 0;
        $nowTs = strtotime($now);
        $today = date('Y-m-d', $nowTs);

        switch ($rule['schedule']) {
            case 'hourly':
                return ($nowTs - $last) >= 3600;
            case 'daily':
                return date('Y-m-d', $last) !== $today;
            case 'weekly':
                $dow = $conds['day_of_week'] ?? 'Monday';
                if (date('l', $nowTs) !== $dow) {
                    return false;
                }
                return date('Y-m-d', $last) !== $today;
            case 'monthly':
                $dom = (int) ($conds['day_of_month'] ?? 1);
                if ((int) date('j', $nowTs) !== $dom) {
                    return false;
                }
                return date('Y-m', $last) !== date('Y-m', $nowTs);
            case 'event':
                return false; // event rules fire via fireEvent()
        }
        return false;
    }

    // ------------------------------------------------- 14.9.3 triggers

    /**
     * Fire an event at all matching active rules (event-triggered rules).
     * Returns [[ruleId, runStatus], ...].
     */
    public static function fireEvent(PDO $db, string $event, array $context): array
    {
        $engine = new self($db);
        $results = [];
        foreach ($engine->rules() as $rule) {
            if ((int) $rule['is_active'] !== 1 || $rule['trigger_type'] !== $event) {
                continue;
            }
            if (!self::conditionsMatch($rule['conditions_json'], $context)) {
                continue;
            }
            $results[] = ['rule_id' => (int) $rule['id'], 'status' => $engine->run($rule, $event, $context)];
        }
        return $results;
    }

    /**
     * 14.9.8 — process all scheduled rules that are due (cron entry point).
     */
    public function runDueRules(?string $now = null): array
    {
        $now ??= Db::now();
        $ran = [];
        foreach ($this->rules() as $rule) {
            if ($rule['trigger_type'] === 'daily_check' && $this->isDue($rule, $now)) {
                $ran[] = ['rule_id' => (int) $rule['id'], 'name' => $rule['name'], 'status' => $this->run($rule, 'daily_check', ['now' => $now])];
            }
        }
        return $ran;
    }

    // ------------------------------------------------- 14.9.4/5/6/7 execution

    /** Execute one rule once: dedup check -> action -> history row. */
    public function run(array $rule, string $trigger, array $context = []): string
    {
        $now = Db::now();
        $fingerprint = $this->fingerprint($rule, $context);

        // 14.9.5 — duplicate prevention within the rule's dedup window
        if ($this->recentSuccess($fingerprint, (int) $rule['dedup_minutes'])) {
            $this->recordRun($rule, $trigger, $fingerprint, 'duplicate_blocked', $now, ['reason' => 'identical execution within dedup window']);
            return 'duplicate_blocked';
        }

        $started = microtime(true);
        try {
            $outcome = $this->executeAction($rule, $context, $trigger);
            $status = 'success';
            $this->recordRun($rule, $trigger, $fingerprint, $status, $now, $outcome + ['duration_ms' => (int) ((microtime(true) - $started) * 1000)]);
            $upd = $this->db->prepare('UPDATE ops_automation_rules SET run_count = run_count + 1, last_run_at = ? WHERE id = ?');
            $upd->execute([$now, $rule['id']]);
        } catch (Throwable $e) {
            // 14.9.7 — failure logging
            $status = 'failed';
            $this->recordRun($rule, $trigger, $fingerprint, $status, $now, ['error' => mb_substr($e->getMessage(), 0, 400)]);
            $upd = $this->db->prepare('UPDATE ops_automation_rules SET fail_count = fail_count + 1, last_run_at = ? WHERE id = ?');
            $upd->execute([$now, $rule['id']]);
        }
        return $status;
    }

    /** Action dispatch (14.9.4). */
    private function executeAction(array $rule, array $context, string $trigger): array
    {
        $config = $rule['action_config_json'] ? json_decode($rule['action_config_json'], true) : [];
        $config = is_array($config) ? $config : [];

        switch ($rule['action_type']) {
            case 'alert_scan':
                $stats = (new Alerts($this->db))->scan(false); // avoid engine recursion
                return ['action' => 'alert_scan', 'created' => $stats['created'], 'updated' => $stats['updated'], 'auto_resolved' => $stats['auto_resolved']];

            case 'generate_report':
                $type = (string) ($config['report_type'] ?? '');
                $payload = (new Reports($this->db))->generate($type, 'automation:' . $rule['name']);
                return ['action' => 'generate_report', 'report_type' => $type, 'snapshot_id' => $payload['meta']['snapshot_id'], 'validation_ok' => $payload['meta']['validation']['ok']];

            case 'log_notification':
                $template = (string) ($config['template'] ?? 'Notification: {title}');
                $message = $template;
                foreach ($context as $k => $v) {
                    $message = str_replace('{' . $k . '}', (string) $v, $message);
                }
                $ins = $this->db->prepare('INSERT INTO ops_alert_events (alert_id, event, details) VALUES (?,?,?)');
                $ins->execute([(int) ($context['alert_id'] ?? 0), 'notification', '[automation:' . $rule['name'] . '] ' . $message]);
                return ['action' => 'log_notification', 'message' => $message];

            case 'raise_alert':
                $alerts = new Alerts($this->db);
                $key = 'automation:' . $rule['id'] . ':' . md5(json_encode($context));
                $existing = $this->db->prepare('SELECT id FROM ops_alerts WHERE alert_key = ?');
                $existing->execute([$key]);
                if ($existing->fetch()) {
                    return ['action' => 'raise_alert', 'deduplicated' => true];
                }
                $ins = $this->db->prepare(
                    'INSERT INTO ops_alerts (alert_key, type, severity, priority_score, entity_type, entity_id, title, message, status, trigger_count, first_triggered_at, last_triggered_at)
                     VALUES (?,?,?,?,?,?,?,?,?,1,?,?)'
                );
                $title = (string) ($config['title'] ?? 'Automated alert: ' . $rule['name']);
                $ins->execute([
                    $key, (string) ($config['type'] ?? 'custom'), (string) ($config['severity'] ?? 'info'),
                    (int) ($config['score'] ?? 25), $context['entity_type'] ?? null, $context['entity_id'] ?? null,
                    $title, (string) ($config['message'] ?? 'Raised by automation rule ' . $rule['name']),
                    'new', Db::now(), Db::now(),
                ]);
                return ['action' => 'raise_alert', 'alert_id' => (int) $this->db->lastInsertId()];
        }
        throw new RuntimeException('Unsupported action type: ' . $rule['action_type']);
    }

    private function fingerprint(array $rule, array $context): string
    {
        $entityKey = ($context['entity_type'] ?? '') . ':' . ($context['entity_id'] ?? ($context['report_type'] ?? $context['title'] ?? 'global'));
        return $rule['id'] . '|' . $rule['action_type'] . '|' . $entityKey . '|' . ($context['alert_id'] ?? '');
    }

    private function recentSuccess(string $fingerprint, int $minutes): bool
    {
        $since = date('Y-m-d H:i:s', time() - max(0, $minutes) * 60);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM ops_automation_runs WHERE fingerprint = ? AND status IN ('success','duplicate_blocked') AND started_at > ?"
        );
        $stmt->execute([$fingerprint, $since]);
        return (bool) $stmt->fetchColumn();
    }

    private function recordRun(array $rule, string $trigger, string $fingerprint, string $status, string $now, array $outcome): void
    {
        $ins = $this->db->prepare(
            'INSERT INTO ops_automation_runs (rule_id, trigger_type, fingerprint, status, started_at, finished_at, outcome_json, error_message)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $error = $status === 'failed' ? ($outcome['error'] ?? 'unknown error') : null;
        $ins->execute([
            $rule['id'], $trigger, $fingerprint, $status, $now, Db::now(),
            json_encode($outcome, JSON_UNESCAPED_SLASHES), $error,
        ]);
    }

    // ------------------------------------------------- 14.9.9 monitoring

    public function monitoring(): array
    {
        $out = [];
        foreach ($this->rules() as $rule) {
            $stats = $this->db->prepare(
                "SELECT COUNT(*) total,
                        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) ok,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) failed,
                        SUM(CASE WHEN status = 'duplicate_blocked' THEN 1 ELSE 0 END) dupes,
                        MAX(started_at) last_run
                 FROM ops_automation_runs WHERE rule_id = ?"
            );
            $stats->execute([$rule['id']]);
            $s = $stats->fetch() ?: [];
            $total = (int) ($s['total'] ?? 0);
            $ok = (int) ($s['ok'] ?? 0);
            $out[] = [
                'rule' => $rule,
                'total_runs' => $total,
                'success' => $ok,
                'failed' => (int) ($s['failed'] ?? 0),
                'duplicates_blocked' => (int) ($s['dupes'] ?? 0),
                'success_rate_pct' => $total > 0 ? round($ok / $total * 100, 1) : null,
                'last_run_at' => $s['last_run'] ?? $rule['last_run_at'],
            ];
        }
        return $out;
    }

    public function runs(int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.name AS rule_name FROM ops_automation_runs r
             LEFT JOIN ops_automation_rules u ON u.id = r.rule_id
             ORDER BY r.id DESC LIMIT ' . max(1, min(500, $limit))
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
