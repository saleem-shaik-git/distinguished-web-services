<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

session_start();
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id, name, email, password_hash, is_active FROM admin_users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if ($admin && (int)$admin['is_active'] === 1 && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid email address or password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login | <?= htmlspecialchars(APP_NAME) ?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-dark"><main class="container min-vh-100 d-flex align-items-center justify-content-center"><div class="card shadow-lg border-0" style="max-width:430px;width:100%"><div class="card-body p-4 p-md-5"><h1 class="h3 fw-bold">Distinguished Web Services</h1><p class="text-muted mb-4">Administrator sign in</p><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="post"><div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required autocomplete="username"></div><div class="mb-4"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required autocomplete="current-password"></div><button class="btn btn-primary w-100">Sign In</button></form></div></div></main></body></html>