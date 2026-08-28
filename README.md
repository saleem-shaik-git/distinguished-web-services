# Distinguished Web Services

Premium PHP/MySQL/Bootstrap portfolio and digital agency website for Distinguished Web Services — plus an internal **Operations Suite (14.6–14.10)**: cost ledger & profitability, predictive risk alerts, automated reports, automation engine and executive BI dashboards. See **[OPS.md](OPS.md)**.

## Phase 1 — Foundation

- Premium dark navy / blue agency visual direction
- Responsive Bootstrap 5 layout
- PHP application entry point
- PDO/MySQL configuration foundation
- Initial services and projects presentation
- Lead enquiry form endpoint foundation
- Initial MySQL schema for services, projects, testimonials, messages and site settings
- Lightweight scroll-reveal interactions
- Basic Apache security headers and routing rules

## Local setup

1. Place the repository under `C:\xampp\htdocs\distinguished-web-services`.
2. Create the database by importing `database/schema.sql` in phpMyAdmin (marketing site tables).
3. Review `config/config.php` and update `APP_URL` and database credentials if needed.
4. Open `http://localhost/distinguished-web-services/`.
5. For the ops console: open `admin/install.php`, run install, then sign in at `admin/index.php` (credentials in [OPS.md](OPS.md)).

## Planned phases

1. Foundation and public homepage
2. Database-backed CMS and admin authentication
3. Dynamic services, projects and case studies
4. Contact/lead management and email notifications
5. SEO, analytics, structured data and conversion tracking
6. Security hardening, testing and production deployment
