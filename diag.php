<?php
declare(strict_types=1);
// Temporary deployment diagnostic — safe to upload, DELETE after use.
// Compatible with PHP 7.4+ so it can also report an outdated PHP version
// instead of fatalling itself.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

echo "DWS deployment diagnostic\n==========================\n\n";

// 1) PHP version
echo 'PHP version: ' . PHP_VERSION . ' — ';
if (PHP_VERSION_ID >= 80100) {
    echo "OK (8.1+, everything supported)\n";
} elseif (PHP_VERSION_ID >= 80000) {
    echo "OK (8.0; ops suite works, 8.1+ recommended)\n";
} elseif (PHP_VERSION_ID >= 70400) {
    echo "MAIN SITE ONLY — ops suite (/admin) needs PHP 8.0+ (match expressions).\n";
    echo "  => cPanel: MultiPHP Manager (or 'Select PHP Version') -> set PHP 8.1/8.2\n";
} else {
    echo "TOO OLD (below 7.4) — upgrade via cPanel MultiPHP Manager.\n";
}

// 2) Required extensions
foreach ([
    'pdo_mysql'  => 'MySQL driver — ops suite default',
    'pdo_sqlite' => 'SQLite driver — ops demo mode (optional)',
    'mbstring'   => 'used by ops suite (optional on tiny paths)',
    'json'       => 'required by ops reports',
] as $ext => $why) {
    printf("ext %-11s: %s — %s\n", $ext, extension_loaded($ext) ? 'loaded    ' : 'MISSING   ', $why);
}

// 3) Critical files actually uploaded?
foreach (['index.php', 'contact.php', 'config/config.php', 'config/ops.php', 'src/bootstrap.php', 'admin/index.php', 'assets/css/style.css'] as $f) {
    printf("file %-22s: %s\n", $f, is_file(__DIR__ . '/' . $f) ? 'present' : 'MISSING — upload incomplete?');
}

// 4) Is index.php the FIXED version?
$index = (string) file_get_contents(__DIR__ . '/index.php');
$hasFixed = strpos($index, "__DIR__ . '/config/config.php'") !== false;
$hasBuggy = strpos($index, "__DIR__ . '/../config/config.php'") !== false;
echo "\nindex.php require path: ";
if ($hasFixed && !$hasBuggy) {
    echo "FIXED (correct)\n";
} elseif ($hasBuggy) {
    echo "BUGGY (../config/config.php) — this alone causes a 500 with a blank page!\n";
    echo "  => re-upload the current index.php from the repo (line 2 fix)\n";
} else {
    echo "unrecognised — check line 2 manually\n";
}

// 5) Writable data dir (SQLite demo mode only)
$dataDir = __DIR__ . '/data';
echo 'data/ directory: ';
if (is_dir($dataDir)) {
    echo is_writable($dataDir) ? "writable (SQLite demo mode active if ops.sqlite exists)\n" : "NOT writable — chmod 755/775 if using SQLite demo mode\n";
} else {
    echo is_writable(__DIR__) ? "absent but can be auto-created\n" : "absent and parent NOT writable\n";
}

// 6) LIVE TEST — render the homepage in-process (catches the real 500 cause)
echo "\n[6] Homepage render test\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
try {
    require __DIR__ . '/index.php';
    $html = ob_get_clean();
    printf("   rendered %d bytes — OK (hero present: %s)\n", strlen($html), strpos($html, 'Digital Solutions') !== false ? 'yes' : 'NO');
} catch (Throwable $e) {
    ob_get_clean();
    printf("   FAILED: %s: %s\n   in %s:%d\n", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
    echo "   ^ THIS is the error behind the 500 on the homepage.\n";
}

// 7) LIVE TEST — ops database connection (what /admin needs)
echo "\n[7] Ops database connection\n";
try {
    require_once __DIR__ . '/src/Db.php';
    $pdo = Db::pdo();
    $ver = (string) $pdo->query($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'SELECT sqlite_version()' : 'SELECT VERSION()')->fetchColumn();
    echo "   driver: " . OPS_DB_DRIVER . " — connected (server version $ver)\n";
} catch (Throwable $e) {
    printf("   driver: %s — FAILED: %s: %s\n", defined('OPS_DB_DRIVER') ? OPS_DB_DRIVER : 'n/a', get_class($e), $e->getMessage());
    echo "   => MySQL: create the DB + user in cPanel 'MySQL Databases', put them in\n";
    echo "      config/config.php (DB_HOST stays 'localhost' on cPanel), then run admin/install.php.\n";
    echo "   => If you uploaded data/ops.sqlite from your own computer: DELETE it from the\n";
    echo "      server (stale demo data / read-only after upload breaks the admin pages).\n";
}

echo "\nHow to read your 500\n--------------------\n";
echo "1. [6] FAILED => the printed error IS the homepage 500 fix it / paste it back.\n";
echo "2. [7] FAILED => admin pages will 500 — fix the database as described above.\n";
echo "3. Both OK but a specific page still 500s => cPanel -> Metrics -> Errors,\n";
echo "   look for the last 'PHP Fatal error' line for that URL.\n";
echo "4. diag.php itself 500s (you can't see this page) => .htaccess problem:\n";
echo "   rename .htaccess to .htaccess.bak and reload.\n";
echo "\nSAPI: " . PHP_SAPI . " | display_errors: " . (string) ini_get('display_errors') . "\n";
echo "Delete diag.php when finished.\n";
