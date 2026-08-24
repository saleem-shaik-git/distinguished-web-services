<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

session_start();
$message = null; $error = null;
$count = (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $count === 0) {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
        $error = 'Enter a name, valid email and password of at least 10 characters.';
    } else {
        $stmt = db()->prepare('INSERT INTO admin_users (name,email,password_hash,role) VALUES (?,?,?,?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'super_admin']);
        $message = 'Administrator created. Delete or protect admin/setup.php before production use.';
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Setup</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="card border-0 shadow-sm mx-auto" style="max-width:520px"><div class="card-body p-4"><h1 class="h3">Create First Administrator</h1><p class="text-muted">Use this once after importing the database schema.</p><?php if ($count > 0 && !$message): ?><div class="alert alert-warning">An administrator already exists. Setup is disabled.</div><?php endif; ?><?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><a href="login.php" class="btn btn-primary">Go to Login</a><?php elseif ($count === 0): ?><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="post"><div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div><div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div><div class="mb-4"><label class="form-label">Password</label><input type="password" name="password" class="form-control" minlength="10" required></div><button class="btn btn-dark w-100">Create Administrator</button></form><?php endif; ?></div></div></main></body></html>