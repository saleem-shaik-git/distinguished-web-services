<?php
// Ops suite bootstrap: strict types, config, services, session, auth guard.
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Schema.php';
require_once __DIR__ . '/Seed.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Ui.php';
require_once __DIR__ . '/Profitability.php';
require_once __DIR__ . '/Alerts.php';
require_once __DIR__ . '/Reports.php';
require_once __DIR__ . '/Automation.php';
require_once __DIR__ . '/Analytics.php';

date_default_timezone_set('Africa/Lagos');

function ops_db(): PDO
{
    try {
        return Db::pdo();
    } catch (Throwable $e) {
        // Convert an opaque 500 into a self-explanatory page.
        http_response_code(500);
        $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        exit('Ops database unavailable (driver: ' . OPS_DB_DRIVER . '): ' . $msg . '<br><br>'
            . 'Fix the credentials in <code>config/config.php</code> (cPanel &rarr; MySQL Databases), '
            . 'or run <a href="install.php">admin/install.php</a>. If you uploaded a local '
            . '<code>data/ops.sqlite</code>, delete it from the server first.');
    }
}

/** HTML-escape helper. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** CSRF token helpers. */
function ops_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['ops_csrf'])) {
        $_SESSION['ops_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['ops_csrf'];
}

function ops_csrf_verify(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return is_string($token) && hash_equals($_SESSION['ops_csrf'] ?? '', $token);
}

function ops_require_csrf(): void
{
    $token = $_POST['csrf'] ?? null;
    if (!ops_csrf_verify(is_string($token) ? $token : null)) {
        http_response_code(419);
        exit('CSRF token mismatch — please reload the page and try again.');
    }
}
