<?php
// Admin UI chrome for the 14.x ops suite (Bootstrap 5 + DWS brand palette).
declare(strict_types=1);

final class Ui
{
    public static function layout(string $title, string $active, string $bodyHtml, ?array $user = null): string
    {
        $nav = [
            'dashboard' => ['dashboard.php', 'bi-speedometer2', 'Executive Dashboard'],
            'alerts' => ['alerts.php', 'bi-exclamation-triangle', 'Risk & Alerts'],
            'profitability' => ['profitability.php', 'bi-cash-stack', 'Cost & Profitability'],
            'reports' => ['reports.php', 'bi-file-earmark-bar-graph', 'Automated Reports'],
            'automation' => ['automation.php', 'bi-gear-wide-connected', 'Automation Engine'],
            'analytics' => ['analytics.php', 'bi-graph-up-arrow', 'BI Analytics'],
            'selftest' => ['selftest.php', 'bi-check2-circle', 'Regression Tests'],
        ];
        $items = '';
        foreach ($nav as $key => [$href, $icon, $label]) {
            $cls = $key === $active ? ' active" style="background:rgba(77,163,255,.15);color:#fff' : '';
            $items .= '<li class="nav-item"><a class="nav-link d-flex align-items-center gap-2 py-2' . ($key === $active ? ' active' : '') . '" href="' . e($href) . '"><i class="bi ' . $icon . '"></i>' . e($label) . '</a></li>';
        }
        $userBox = $user
            ? '<div class="ms-auto d-flex align-items-center gap-3"><span class="small text-secondary">' . e($user['name']) . ' <span class="badge text-bg-light">' . e($user['role']) . '</span></span><a class="btn btn-outline-light btn-sm" href="index.php?logout=1">Sign out</a></div>'
            : '';

        return '<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . e($title) . ' | ' . e(OPS_COMPANY) . ' Ops</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--navy:#07111f;--navy2:#0b1628;--blue:#4da3ff;--blue2:#2278d2}
body{background:#f4f6fa;font-family:Inter,system-ui,sans-serif;color:#16233a}
.site-nav{background:var(--navy)}
.sidebar{background:var(--navy);min-height:calc(100vh - 56px)}
.sidebar .nav-link{color:#aebccd;font-size:.92rem;border-radius:10px;padding:.55rem .9rem}
.sidebar .nav-link:hover{color:#fff;background:rgba(255,255,255,.05)}
.sidebar .nav-link.active{color:#fff;background:rgba(77,163,255,.16)}
.card{border:0;border-radius:16px;box-shadow:0 10px 30px rgba(12,28,50,.06)}
.kpi{font-size:1.65rem;font-weight:800;letter-spacing:-.02em}
.kpi-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;color:#7c8ba0;font-weight:700}
.sev-critical{background:#fde8e8;color:#b42318}.sev-warning{background:#fff4e0;color:#b54708}.sev-info{background:#e4f0ff;color:#175cd3}
.pill{font-size:.72rem;font-weight:800;padding:.25rem .6rem;border-radius:999px}
.table td,.table th{vertical-align:middle}
.bar{height:8px;border-radius:99px;background:#e6ebf2;overflow:hidden}.bar>span{display:block;height:100%;border-radius:99px}
footer.ops-footer{font-size:.8rem;color:#8a99ad}
</style></head><body>
<nav class="navbar navbar-expand site-nav navbar-dark py-2 sticky-top">
  <div class="container-fluid px-3 px-lg-4">
    <a class="navbar-brand fw-bold" href="dashboard.php"><span style="color:var(--blue)">DW</span> Ops Console</a>
    ' . $userBox . '
  </div>
</nav>
<div class="container-fluid">
<div class="row">
  <aside class="col-lg-2 d-none d-lg-block sidebar py-3"><ul class="nav flex-column gap-1 px-3">' . $items . '</ul></aside>
  <main class="col-lg-10 px-3 px-lg-4 py-4">' . $bodyHtml . '
    <footer class="ops-footer mt-5 pb-3">Distinguished Web Services — Operations Suite 14.6–14.10 · schema v14.10 · ' . date('Y') . '</footer>
  </main>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>';
    }

    public static function kpiCard(string $label, string $value, string $sub = '', string $tone = 'dark'): string
    {
        $color = ['dark' => '#16233a', 'blue' => '#2278d2', 'red' => '#b42318', 'green' => '#067647', 'amber' => '#b54708'][$tone] ?? '#16233a';
        return '<div class="card h-100"><div class="card-body py-3">
            <div class="kpi-label">' . e($label) . '</div>
            <div class="kpi" style="color:' . $color . '">' . $value . '</div>
            ' . ($sub !== '' ? '<div class="small text-secondary mt-1">' . $sub . '</div>' : '') . '
        </div></div>';
    }

    public static function severityBadge(string $severity): string
    {
        return '<span class="pill sev-' . e($severity) . '">' . strtoupper(e($severity)) . '</span>';
    }

    public static function statusBadge(string $status): string
    {
        $map = ['new' => 'text-bg-primary', 'acknowledged' => 'text-bg-warning', 'resolved' => 'text-bg-secondary',
                'success' => 'text-bg-success', 'failed' => 'text-bg-danger', 'duplicate_blocked' => 'text-bg-info',
                'final' => 'text-bg-success', 'draft' => 'text-bg-warning', 'active' => 'text-bg-success', 'inactive' => 'text-bg-secondary'];
        return '<span class="badge ' . ($map[$status] ?? 'text-bg-light') . '">' . e($status) . '</span>';
    }
}
