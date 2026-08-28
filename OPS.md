# Distinguished Web Services — Operations Suite (14.6 – 14.10)

Internal business-management console: project cost ledger & profitability,
predictive risk alerts, automated reports, an automation engine and executive
BI dashboards. Plain PHP 8 + PDO + Bootstrap 5 — no frameworks, no Composer.

## Quick start (XAMPP)

1. Open `http://localhost/distinguished-web-services/admin/install.php` → **Run install**.
   - MySQL is used by default (credentials from `config/config.php`), creating 15 `ops_*` tables alongside the existing marketing schema.
2. Sign in at `admin/index.php` — seeded admin:
   - `admin@distinguishedwebservices.com` / `DwsAdmin#2026` *(change immediately in production)*
   - analyst account: `analyst@distinguishedwebservices.com` / `DwsAnalyst#2026`
3. Browse the console: Dashboard → Risk & Alerts → Cost & Profitability → Automated Reports → Automation Engine → BI Analytics → Regression Tests.

**Demo/SQLite mode:** if `data/ops.sqlite` exists (or `OPS_DB_DRIVER=sqlite`), the suite
runs on SQLite — zero MySQL setup. Delete the file to return to MySQL. Both drivers use
the same application SQL; the schema is defined once (MySQL DDL) and auto-transformed.

## Feature map

| Spec | Feature | Where |
|---|---|---|
| 14.6.1 | Project cost ledger | `ops_cost_entries`, `Profitability::ledger()/ledgerSummary()`, profitability page |
| 14.6.2 | Project profitability view | `Profitability::projectProfitability()` |
| 14.6.3 | Profitability KPIs | `Profitability::kpis()` |
| 14.6.4 | Low-margin detection | `Profitability::lowMargin()` (< 15%, configurable) |
| 14.6.5 | Over-budget detection | `Profitability::overBudget()` |
| 14.7.1–.7 | Overdue invoice/project/task, SLA risk, low-margin, over-budget, CRM follow-up detectors | `Alerts::detect*()` |
| 14.7.8 | Alert prioritization | `Alerts::priorityScore()/priorityTier()` (P1–P4) |
| 14.7.9 | Alert deduplication | `Alerts::scan()` — re-detection bumps `trigger_count`, auto-resolves cleared conditions |
| 14.7.10 | Automation logging/testing | `ops_alert_events` + engine dispatch + suite |
| 14.8.1–.8 | 8 report generators | `Reports::TYPES` / `Reports::generate()` |
| 14.8.9 | Historical snapshots | `ops_report_snapshots` (retained, viewable) |
| 14.8.10 | Report validation | `Reports::validate()` — blocks invalid payloads from finalising |
| 14.9.1 | Automation rules | `ops_automation_rules` |
| 14.9.2 | Rule evaluation | `Automation::conditionsMatch()` |
| 14.9.3 | Trigger processing | `Automation::fireEvent()` / `runDueRules()` |
| 14.9.4 | Action execution | `alert_scan`, `generate_report`, `log_notification`, `raise_alert` |
| 14.9.5 | Duplicate prevention | fingerprint + per-rule dedup window |
| 14.9.6 | Execution history | `ops_automation_runs` |
| 14.9.7 | Failure logging | failed runs + `error_message` + `fail_count` |
| 14.9.8 | Scheduled execution | `cron.php` (CLI or `?token=…`), daily/weekly/monthly due logic |
| 14.9.9 | Automation monitoring | `Automation::monitoring()` + console page |
| 14.9.10 | Production testing | `tests/run.php` + admin selftest page |
| 14.10.1 | Executive KPI dashboard | `Analytics::execKpis()` |
| 14.10.2–.7 | Sales/Finance/Projects/Team/Support/Profitability analytics | `Analytics::*()` |
| 14.10.8 | Predictive alerts dashboard | `Analytics::alertsView()` |
| 14.10.9 | Reports integration | `Analytics::reportsIntegration()` (latest snapshot per type on dashboard) |
| 14.10.10 | Final production regression | `tests/run.php` — 44 assertions covering every item above |

## Tests

```
php tests/run.php          # CLI (exit code 0 = green)
# or: admin/selftest.php in the console
```

Runs against a throwaway SQLite database (`data/ops-test.sqlite`) — never touches live data.

## Scheduling (14.9.8)

Windows: `schtasks /create /tn "DWS Ops" /tr "php C:\xampp\htdocs\distinguished-web-services\cron.php" /sc hourly`
Linux: `0 * * * * php /path/to/cron.php`

`cron.php` processes due rules, runs a safety-net alert scan, and prunes snapshots
(500/type). Web invocation requires `?token=` matching `OPS_CRON_TOKEN` in `config/ops.php`.

## Security notes

- Admin session auth (bcrypt), CSRF token on every console POST, output escaped everywhere, parameterised SQL only.
- Marketing site (`index.php`, `contact.php`, public schema in `database/schema.sql`) is untouched; everything ops-related lives behind `/admin`, `/src`, `/cron.php` and `ops_*` tables.
- Rotate the seeded passwords and `OPS_CRON_TOKEN` before production; consider HTTP basic-auth or IP allowlisting on `/admin` as an extra layer.
