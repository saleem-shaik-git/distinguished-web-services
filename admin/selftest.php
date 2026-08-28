<?php
declare(strict_types=1);
// 14.10.10 — run the full acceptance/regression suite from the console.
require_once __DIR__ . '/../src/bootstrap.php';
$user = Auth::requireLogin();
define('OPS_WEBTEST', true);
require_once __DIR__ . '/../tests/run.php';

$runner = new OpsTestRunner();
$summary = $runner->run();

$body = '<h1 class="h4 fw-bold mb-1">Regression &amp; Acceptance Tests</h1>
<p class="text-secondary">Executes the full 14.6–14.10 suite against a throwaway test database (never touches live data): '
. (int) $summary['total'] . ' checks across 5 groups.</p>';
$body .= '<div class="alert ' . ($summary['ok'] ? 'alert-success' : 'alert-danger') . ' py-2 fw-bold">'
. ($summary['ok'] ? '<i class="bi bi-check-circle-fill"></i> ALL ' . $summary['passed'] . ' TESTS PASSED' : '<i class="bi bi-x-circle-fill"></i> ' . $summary['failed'] . ' test(s) failed — ' . $summary['passed'] . '/' . $summary['total'] . ' passed')
. '</div>';

$lastGroup = null;
foreach ($summary['results'] as $r) {
    if ($r['group'] !== $lastGroup) {
        $body .= '<h2 class="h6 fw-bold mt-4 mb-2">' . e($r['group']) . '</h2>';
        $lastGroup = $r['group'];
    }
    $body .= '<div class="d-flex gap-2 align-items-start border-bottom py-2">
        <span class="badge ' . ($r['ok'] ? 'text-bg-success' : 'text-bg-danger') . '">' . ($r['ok'] ? 'PASS' : 'FAIL') . '</span>
        <div><span class="fw-bold">' . e($r['id']) . '</span> — ' . e($r['name'])
        . ($r['error'] ? '<div class="text-danger small" style="font-size:.78rem">' . e($r['error']) . '</div>' : '') . '</div></div>';
}
echo Ui::layout('Regression Tests', 'selftest', $body, $user);
