<?php
// Minimal, dependency-free admin auth (ops_admins + bcrypt + sessions).
declare(strict_types=1);

final class Auth
{
    public static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function attempt(PDO $db, string $email, string $password): ?array
    {
        $stmt = $db->prepare('SELECT * FROM ops_admins WHERE email = ? AND is_active = 1');
        $stmt->execute([mb_substr(trim($email), 0, 190)]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['ops_admin_id'] = (int) $admin['id'];
            $_SESSION['ops_admin_name'] = $admin['name'];
            $_SESSION['ops_admin_role'] = $admin['role'];
            $_SESSION['ops_admin_email'] = $admin['email'];
            return $admin;
        }
        // throttle: small delay on failure
        usleep(250000);
        return null;
    }

    public static function user(): ?array
    {
        self::ensureSession();
        if (empty($_SESSION['ops_admin_id'])) {
            return null;
        }
        return [
            'id' => (int) $_SESSION['ops_admin_id'],
            'name' => (string) ($_SESSION['ops_admin_name'] ?? ''),
            'role' => (string) ($_SESSION['ops_admin_role'] ?? 'admin'),
            'email' => (string) ($_SESSION['ops_admin_email'] ?? ''),
        ];
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            header('Location: index.php');
            exit;
        }
        return $user;
    }

    public static function logout(): void
    {
        self::ensureSession();
        $_SESSION = [];
        session_destroy();
    }
}
