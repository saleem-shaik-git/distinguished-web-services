<?php
// Distinguished Web Services — Operations suite configuration (14.x)
// Driver resolution:
//   1. OPS_DB_DRIVER environment variable (force 'mysql' or 'sqlite')
//   2. If data/ops.sqlite exists -> 'sqlite' (demo mode)
//   3. Otherwise 'mysql' (XAMPP/production default, credentials in config.php)
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Driver resolution (runtime value — hence define() instead of const):
//   1. OPS_DB_DRIVER environment variable (force 'mysql' or 'sqlite')
//   2. If data/ops.sqlite exists -> 'sqlite' (demo mode)
//   3. Otherwise 'mysql' (XAMPP/production default, credentials in config.php)
(static function (): void {
    $driver = getenv('OPS_DB_DRIVER');
    if ($driver !== 'mysql' && $driver !== 'sqlite') {
        $driver = is_file(__DIR__ . '/../data/ops.sqlite') ? 'sqlite' : 'mysql';
    }
    define('OPS_DB_DRIVER', $driver);
})();

const OPS_DATA_DIR = __DIR__ . '/../data';
const OPS_SQLITE_PATH = OPS_DATA_DIR . '/ops.sqlite';
const OPS_TEST_SQLITE_PATH = OPS_DATA_DIR . '/ops-test.sqlite';

// Executive/ops suite constants
const OPS_COMPANY = APP_NAME;
const OPS_CURRENCY = 'NGN';
const OPS_LOW_MARGIN_THRESHOLD = 15.0;   // 14.6.4 — margin % below this = low-margin
const OPS_OVERBUDGET_THRESHOLD = 100.0;  // 14.6.5 — cost % of budget above this = over-budget
const OPS_CRON_TOKEN = 'change-me-dws-ops-cron'; // Used by cron.php (?token=...) — change in production
const OPS_AUTOMATION_DEDUP_MINUTES = 1440;       // 14.9.5 duplicate-prevention window

function ops_money(float|int|string|null $amount, string $symbol = '₦'): string
{
    return $symbol . number_format((float) $amount, 2);
}

function ops_pct(float|int|string|null $value, int $decimals = 1): string
{
    return number_format((float) $value, $decimals) . '%';
}
