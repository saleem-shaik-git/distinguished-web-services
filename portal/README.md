# Client Portal Production Integration

Phase 13 hardening baseline:

- Use HTTPS in production.
- Set secure, HttpOnly and SameSite session cookies.
- Use CSRF tokens on all state-changing forms.
- Authorize every project, ticket, approval, document and invoice through the authenticated client's client_id.
- Never trust client_id supplied by the browser.
- Use prepared SQL statements.
- Keep uploaded files outside the public web root and serve them through an authorization-controlled download endpoint.
- Use password_hash/password_verify for portal credentials.
- Rotate sessions after authentication.
- Rate-limit login and password reset endpoints.
- Configure SMTP and payment provider credentials through environment variables, never source control.
- Run database/023_phase13_client_portal.sql before enabling the portal.
- Test invoice/payment links against the existing invoice/payment source of truth; do not duplicate payment records.
- Test proposal/approval actions against existing approval records and audit every decision.
- Enable daily database backups and verify restore procedures before production launch.
