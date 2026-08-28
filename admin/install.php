<?php
declare(strict_types=1);
// One-click installer: creates all ops_* tables (MySQL or SQLite) and loads
// deterministic demo data. Re-running is safe (idempotent refresh).
require_once __DIR__ . '/../src/bootstrap.php';

$done = false;
$results = [];
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ops_require_csrf();
    try {
        $pdo = Db::pdo();
        $results['tables'] = Schema::migrate($pdo, OPS_DB_DRIVER);
        $results['seed'] = Seed::run($pdo);
        $done = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$token = ops_csrf_token();
$body = '<h1 class="h4 fw-bold mb-1">Install / Refresh Ops Suite</h1>
<p class="text-secondary">Driver: <span class="badge text-bg-dark">' . e(OPS_DB_DRIVER) . '</span> · creates 15 <code>ops_*</code> tables and loads deterministic demo data (overdue invoices, low-margin &amp; over-budget projects, SLA breaches, due follow-ups, 6 automation rules).</p>'
. ($error ? '<div class="alert alert-danger"><strong>Install failed:</strong> ' . e($error) . '</div>' : '')
. ($done ? '<div class="alert alert-success"><strong>Installed.</strong> ' . count($results['tables']) . ' tables verified. Seeded admin: <code>' . e($results['seed']['admin_email']) . '</code> / <code>' . e($results['seed']['admin_password']) . '</code> — <a href="index.php">sign in now</a>.</div>' : '')
. '<form method="post" class="d-flex gap-2 align-items-center">
   <input type="hidden" name="csrf" value="' . e($token) . '">
   <button class="btn btn-primary fw-bold">Run install / refresh seed</button>
   <span class="small text-secondary">Warning: refreshes demo data (ops_* tables only — marketing site data untouched).</span></form>'
. ($done ? '<hr><h2 class="h6 fw-bold">Seeded</h2><div class="row g-3">' . implode('', array_map(fn ($k, $v) => is_int($v)
      ? '<div class="col-6 col-md-3"><div class="kpi-label">' . e((string) $k) . '</div><div class="fw-bold fs-5">' . $v . '</div></div>'
      : '', array_keys($results['seed']), $results['seed'])) . '</div>' : '');
echo Ui::layout('Install', '', $body);
