<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
Auth::ensureSession();

if (isset($_GET['logout'])) {
    Auth::logout();
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ops_require_csrf();
    $email = (string) ($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    try {
        if (Auth::attempt(ops_db(), $email, $password)) {
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid credentials.';
    } catch (Throwable $e) {
        $error = 'Database not ready — run <a href="install.php">install.php</a> first. (' . e($e->getMessage()) . ')';
    }
}
$token = ops_csrf_token();
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in | <?= e(OPS_COMPANY) ?> Ops</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:radial-gradient(circle at 80% 20%,rgba(34,120,210,.25),transparent 40%),#07111f;font-family:Inter,system-ui,sans-serif}.card{border:0;border-radius:18px}</style>
</head><body class="min-vh-100 d-flex align-items-center">
<div class="container" style="max-width:420px">
  <div class="text-center text-white mb-4"><div style="font-size:2rem;font-weight:800"><span style="color:#4da3ff">DW</span> Ops Console</div><div class="small text-white-50">Distinguished Web Services · Operations Suite 14.6–14.10</div></div>
  <div class="card shadow-lg"><div class="card-body p-4">
    <h5 class="fw-bold mb-3">Sign in</h5>
    <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= $error ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e($token) ?>">
      <div class="mb-3"><label class="form-label small fw-bold">Email</label><input type="email" name="email" class="form-control" required autofocus></div>
      <div class="mb-3"><label class="form-label small fw-bold">Password</label><input type="password" name="password" class="form-control" required></div>
      <button class="btn btn-primary w-100 fw-bold">Sign in</button>
    </form>
    <hr><div class="small text-secondary">First time? Run <a href="install.php">install.php</a> to create tables &amp; demo data.</div>
  </div></div>
</div></body></html>
