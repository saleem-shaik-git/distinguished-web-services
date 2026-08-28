<?php
declare(strict_types=1);
// 14.9.8 — scheduled execution entry point.
// XAMPP/Windows:  schtasks /create /tn "DWS Ops" /tr "php C:\xampp\htdocs\distinguished-web-services\cron.php" /sc hourly
// Linux/cron:     0 * * * * php /path/to/cron.php
// Web (dev only): https://host/cron.php?token=OPS_CRON_TOKEN
require_once __DIR__ . '/src/bootstrap.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $token = (string) ($_GET['token'] ?? '');
    if (!hash_equals(OPS_CRON_TOKEN, $token)) {
        http_response_code(403);
        exit('Forbidden — cron token required.');
    }
}

$log = static function (string $line) use ($isCli): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL;
};

try {
    $pdo = Db::pdo();
    $started = microtime(true);

    // 1) Scheduled automation rules (daily risk scan, report generation, ...)
    $engine = new Automation($pdo);
    $ran = $engine->runDueRules();
    $log('automation: ' . count($ran) . ' due rule(s) processed');
    foreach ($ran as $r) {
        $log('  - rule #' . $r['rule_id'] . ' "' . $r['name'] . '" -> ' . $r['status']);
    }

    // 2) Safety net: run an alert scan even if no rule covers it
    $before = (int) $pdo->query("SELECT COUNT(*) FROM ops_alerts WHERE status IN ('new','acknowledged')")->fetchColumn();
    $scan = (new Alerts($pdo))->scan();
    $after = (int) $pdo->query("SELECT COUNT(*) FROM ops_alerts WHERE status IN ('new','acknowledged')")->fetchColumn();
    $log(sprintf('alerts: %d conditions -> %d new, %d updated, %d auto-resolved (open %d -> %d)',
        $scan['candidates'], $scan['created'], $scan['updated'], $scan['auto_resolved'], $before, $after));

    // 3) Housekeeping: keep report snapshots bounded (latest 500 per type)
    $pdo->exec("DELETE FROM ops_report_snapshots WHERE id NOT IN (
        SELECT id FROM (SELECT id, ROW_NUMBER() OVER (PARTITION BY report_type ORDER BY generated_at DESC, id DESC) rn
                        FROM ops_report_snapshots) ranked WHERE rn <= 500)");

    $log(sprintf('done in %d ms', (int) ((microtime(true) - $started) * 1000)));
    $isCli || http_response_code(200);
} catch (Throwable $e) {
    $log('CRON ERROR: ' . $e->getMessage());
    if (!$isCli) {
        http_response_code(500);
    }
    exit(1);
}
