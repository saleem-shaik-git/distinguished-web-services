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

echo "\nHow to read your 500\n--------------------\n";
echo "1. EVERYTHING 500s (even this diag.php and /assets/css/style.css)\n";
echo "   => .htaccess problem. Rename .htaccess to .htaccess.bak and reload.\n";
echo "      If it works: use the updated .htaccess from the repo (mod_alias guard).\n";
echo "2. Only / 500s\n";
echo "   => index.php path bug or truncated upload — see 'require path' check above.\n";
echo "3. Homepage fine, only /admin/... 500s\n";
echo "   => PHP below 8.0 or a missing PDO driver — see version/extension checks.\n";
echo "4. Exact reason: cPanel -> Metrics -> Errors. Look for 'PHP Fatal error' or\n";
echo "   'Invalid command' (Invalid command = .htaccess directive).\n";
echo "\nSAPI: " . PHP_SAPI . " | display_errors: " . (string) ini_get('display_errors') . "\n";
echo "Delete diag.php when finished.\n";
