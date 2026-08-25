# Phase 13 Production Regression Checklist

## Automated
Run from the project root:

`C:\xampp\php\php.exe tests\phase13-production-regression.php`

The script checks the Phase 13 tables, database access, password hashing, CSRF entropy and client-scoped authorization queries. Exit code 0 means the automated suite passed.

## Browser smoke test

1. Open `/portal/login.php`.
2. Confirm invalid credentials are rejected.
3. Confirm repeated failures are throttled.
4. Log in with a real portal user.
5. Confirm session regeneration/login succeeds.
6. Open dashboard and verify only that client's counts appear.
7. Open Projects and verify another client's project cannot be accessed by changing the ID.
8. Open Documents and verify only visible documents for the client are listed.
9. Download a document and verify authorization plus activity logging.
10. Open Approvals and verify the client can see only approvals attached to its projects.
11. Approve/reject a test approval and verify the existing approval source of truth changes correctly.
12. Open Invoices and verify invoice totals/status come from the existing invoice tables.
13. Start a test payment using the existing payment integration and verify webhook/idempotency behavior in test mode.
14. Open Support and create a test ticket; verify it is scoped to the client.
15. Reply to the ticket and verify the communication/audit trail.
16. Open Messages and verify client/staff messages remain client-scoped.
17. Open Profile/Settings and verify changes persist.
18. Log out and confirm protected portal pages redirect to login.
19. Open `/portal/health.php` and verify a healthy database response; restrict this endpoint before public launch.

## Security checks

- HTTPS enabled in production.
- `display_errors=Off` in production.
- Secrets are not committed to Git.
- Upload directory is outside the public web root or protected by server rules.
- Database backups and restore test completed.
- Payment credentials are live only after successful test-mode regression.
- Error logs do not expose SQL, credentials or session data.
