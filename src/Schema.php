<?php
// 14.x operations schema — single MySQL DDL source of truth, auto-transformed
// for SQLite (demo/test driver). All tables prefixed ops_ so the public
// marketing schema (schema.sql) remains untouched.
declare(strict_types=1);

final class Schema
{
    /** @return string[] created/verified tables */
    public static function migrate(PDO $pdo, string $driver): array
    {
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = OFF');
        }
        $created = [];
        foreach (self::tables() as $table => $ddl) {
            if ($driver === 'sqlite') {
                [$sqliteDdl, $indexes] = self::toSqlite($ddl, $table);
                $pdo->exec($sqliteDdl);
                foreach ($indexes as $idxSql) {
                    $pdo->exec($idxSql);
                }
            } else {
                $pdo->exec($ddl);
            }
            $created[] = $table;
        }
        self::setting($pdo, 'schema_version', '14.10');
        self::setting($pdo, 'migrated_at', Db::now());
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        return $created;
    }

    private static function setting(PDO $pdo, string $key, string $value): void
    {
        $stmt = $pdo->prepare('SELECT id FROM ops_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        if ($stmt->fetch()) {
            $upd = $pdo->prepare('UPDATE ops_settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?');
            $upd->execute([$value, Db::now(), $key]);
        } else {
            $ins = $pdo->prepare('INSERT INTO ops_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)');
            $ins->execute([$key, $value, Db::now()]);
        }
    }

    /** @return array<string,string> table => MySQL DDL */
    private static function tables(): array
    {
        $pk = 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        return [
            // ---- foundation (supporting core for 14.6–14.10) ----
            'ops_admins' => "CREATE TABLE IF NOT EXISTS ops_admins (
                id $pk,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(30) NOT NULL DEFAULT 'admin',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ops_clients' => "CREATE TABLE IF NOT EXISTS ops_clients (
                id $pk,
                name VARCHAR(180) NOT NULL,
                contact_name VARCHAR(150) NULL,
                email VARCHAR(190) NULL,
                phone VARCHAR(50) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ops_team' => "CREATE TABLE IF NOT EXISTS ops_team (
                id $pk,
                name VARCHAR(150) NOT NULL,
                role VARCHAR(120) NULL,
                hourly_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // 14.6.1 — project cost ledger is written here
            'ops_projects' => "CREATE TABLE IF NOT EXISTS ops_projects (
                id $pk,
                client_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(200) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                billing_type VARCHAR(20) NOT NULL DEFAULT 'fixed',
                budget_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
                start_date DATE NOT NULL,
                due_date DATE NOT NULL,
                completed_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ops_projects_status (status),
                INDEX idx_ops_projects_due (due_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ops_cost_entries' => "CREATE TABLE IF NOT EXISTS ops_cost_entries (
                id $pk,
                project_id BIGINT UNSIGNED NOT NULL,
                category VARCHAR(40) NOT NULL,
                description VARCHAR(300) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                cost_date DATE NOT NULL,
                team_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ops_cost_project (project_id),
                INDEX idx_ops_cost_date (cost_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ops_invoices' => "CREATE TABLE IF NOT EXISTS ops_invoices (
                id $pk,
                invoice_number VARCHAR(60) NOT NULL UNIQUE,
                client_id BIGINT UNSIGNED NOT NULL,
                project_id BIGINT UNSIGNED NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                issue_date DATE NOT NULL,
                due_date DATE NOT NULL,
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
                tax DECIMAL(12,2) NOT NULL DEFAULT 0,
                total DECIMAL(12,2) NOT NULL DEFAULT 0,
                paid_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ops_inv_status (status),
                INDEX idx_ops_inv_due (due_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ops_tasks' => "CREATE TABLE IF NOT EXISTS ops_tasks (
                id $pk,
                project_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(250) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'todo',
                team_id BIGINT UNSIGNED NULL,
                due_date DATE NOT NULL,
                completed_at DATETIME NULL,
                estimate_hours DECIMAL(6,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ops_tasks_status (status),
                INDEX idx_ops_tasks_due (due_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ops_leads' => "CREATE TABLE IF NOT EXISTS ops_leads (
                id $pk,
                name VARCHAR(150) NOT NULL,
                company VARCHAR(180) NULL,
                email VARCHAR(190) NULL,
                phone VARCHAR(50) NULL,
                stage VARCHAR(30) NOT NULL DEFAULT 'new',
                value_estimate DECIMAL(12,2) NOT NULL DEFAULT 0,
                last_contacted_at DATETIME NULL,
                next_followup_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ops_leads_stage (stage)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ops_tickets' => "CREATE TABLE IF NOT EXISTS ops_tickets (
                id $pk,
                client_id BIGINT UNSIGNED NOT NULL,
                subject VARCHAR(250) NOT NULL,
                priority VARCHAR(20) NOT NULL DEFAULT 'normal',
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                sla_due_at DATETIME NOT NULL,
                first_response_at DATETIME NULL,
                resolved_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ops_tickets_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // 14.7 — predictive alerts (+ events for automation logging/testing)
            'ops_alerts' => "CREATE TABLE IF NOT EXISTS ops_alerts (
                id $pk,
                alert_key VARCHAR(220) NOT NULL UNIQUE,
                type VARCHAR(40) NOT NULL,
                severity VARCHAR(20) NOT NULL DEFAULT 'warning',
                priority_score INT NOT NULL DEFAULT 0,
                entity_type VARCHAR(40) NULL,
                entity_id BIGINT UNSIGNED NULL,
                title VARCHAR(250) NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'new',
                trigger_count INT NOT NULL DEFAULT 1,
                first_triggered_at DATETIME NOT NULL,
                last_triggered_at DATETIME NOT NULL,
                resolved_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ops_alerts_status (status),
                INDEX idx_ops_alerts_severity (severity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ops_alert_events' => "CREATE TABLE IF NOT EXISTS ops_alert_events (
                id $pk,
                alert_id BIGINT UNSIGNED NOT NULL,
                event VARCHAR(30) NOT NULL,
                details VARCHAR(500) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // 14.8 — report snapshots (historical)
            'ops_report_snapshots' => "CREATE TABLE IF NOT EXISTS ops_report_snapshots (
                id $pk,
                report_type VARCHAR(60) NOT NULL,
                period_label VARCHAR(80) NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                generated_by VARCHAR(60) NOT NULL DEFAULT 'system',
                generated_at DATETIME NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'final',
                payload_json TEXT NOT NULL,
                validation_ok TINYINT(1) NOT NULL DEFAULT 1,
                validation_errors TEXT NULL,
                row_count INT NOT NULL DEFAULT 0,
                INDEX idx_ops_snap_type (report_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // 14.9 — automation engine
            'ops_automation_rules' => "CREATE TABLE IF NOT EXISTS ops_automation_rules (
                id $pk,
                name VARCHAR(180) NOT NULL,
                trigger_type VARCHAR(40) NOT NULL,
                conditions_json TEXT NULL,
                action_type VARCHAR(40) NOT NULL,
                action_config_json TEXT NULL,
                schedule VARCHAR(20) NOT NULL DEFAULT 'daily',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                dedup_minutes INT NOT NULL DEFAULT 1440,
                last_run_at DATETIME NULL,
                run_count INT NOT NULL DEFAULT 0,
                fail_count INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ops_automation_runs' => "CREATE TABLE IF NOT EXISTS ops_automation_runs (
                id $pk,
                rule_id BIGINT UNSIGNED NOT NULL,
                trigger_type VARCHAR(40) NOT NULL,
                fingerprint VARCHAR(250) NOT NULL,
                status VARCHAR(30) NOT NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME NULL,
                outcome_json TEXT NULL,
                error_message TEXT NULL,
                INDEX idx_ops_runs_rule (rule_id),
                INDEX idx_ops_runs_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ops_settings' => "CREATE TABLE IF NOT EXISTS ops_settings (
                id $pk,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    /**
     * Transform MySQL DDL to SQLite-compatible DDL. Inline INDEX
     * declarations (not supported inside SQLite CREATE TABLE) are extracted
     * into separate CREATE INDEX statements: returns [ddl, indexStatements].
     *
     * @return array{0: string, 1: string[]}
     */
    public static function toSqlite(string $mysql, string $table = 't'): array
    {
        $s = $mysql;
        $indexes = [];
        // pull out inline index declarations first (they may end with a comma)
        $s = preg_replace_callback(
            '/,?\s*INDEX\s+(\w+)\s*\(([^)]+)\)/i',
            function ($m) use (&$indexes, $table) {
                $indexes[] = 'CREATE INDEX IF NOT EXISTS ' . $m[1] . ' ON ' . $table . ' (' . $m[2] . ')';
                return '';
            },
            $s
        );
        $s = preg_replace('/BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $s);
        $s = preg_replace('/\bENUM\([^)]*\)/i', 'TEXT', $s);
        $s = preg_replace('/\s+ON UPDATE CURRENT_TIMESTAMP/i', '', $s);
        $s = preg_replace('/\)\s*ENGINE=.*$/is', ')', $s);
        $s = preg_replace('/,\s*(\)|\s*$)/i', '$1', $s); // trailing commas from removed indexes
        return [$s, $indexes];
    }
}
