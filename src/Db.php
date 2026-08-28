<?php
// PDO factory for the ops suite. Works with MySQL (XAMPP/production) and
// SQLite (demo/tests) using identical application-level SQL.
declare(strict_types=1);

require_once __DIR__ . '/../config/ops.php';

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        return self::$pdo = self::connect(OPS_DB_DRIVER);
    }

    public static function connect(string $driver, ?string $sqlitePath = null): PDO
    {
        if ($driver === 'sqlite') {
            $path = $sqlitePath ?? OPS_SQLITE_PATH;
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
            return $pdo;
        }

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /** Portable date-time helper (both drivers store 'Y-m-d H:i:s'). */
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function today(): string
    {
        return date('Y-m-d');
    }
}
