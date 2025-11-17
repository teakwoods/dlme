# Security Overview (DLme)

This project is a Dockerized WordPress / WooCommerce / Bookings marketplace.
Security is defense in depth across Docker, WordPress, and the hosting environment.

## 1. Docker & Infrastructure

- Only the nginx container exposes a host port.
- The database (db) and php-fpm (wordpress) containers are only reachable on the internal `wpnet` network.
- Application code is baked into the production image and mounted read-only to nginx in production.
- Secrets (DB credentials, salts, admin email, etc.) are provided via:
  - `.env` (dev/staging only), and
  - Environment variables or secret management in production.
- No real secrets are committed to the repo.

Any VPS with Docker installed can run this stack via:

```bash
docker compose up -d
```

Host-level firewalls are responsible for:

- Restricting inbound ports (typically 80/443 only).
- Optionally restricting origin access to reverse-proxy IP ranges (e.g., Cloudflare) in production.

## 2. WordPress Hardening

The `wp-config.php` should be configured with:

- `WP_ENV` environment variable to distinguish `development`, `staging`, and `production`.
- In `production`:
  - `DISALLOW_FILE_EDIT` enabled.
  - `DISALLOW_FILE_MODS` enabled (no plugin/theme/code changes via the admin UI).
  - `WP_DEBUG` and `WP_DEBUG_DISPLAY` off.
  - `FORCE_SSL_ADMIN` on.
- `WP_HOME` and `WP_SITEURL` set from the `WP_URL` environment variable.

Accounts & roles:

- One primary admin account with strong password + 2FA.
- Vendors and staff get least-privilege roles only.
- No shared admin logins.

## 3. Dependencies & Updates

- PHP dev dependencies are managed via `composer.json` and `composer.lock`.
- WordPress core, WooCommerce, Bookings, and vendor plugins should be updated on a regular schedule via:
  - dev → staging → production using Docker images, not manual updates in production.

CI must pass before merging changes to `main`:

- `composer lint` (PHPCS)
- `composer analyse` (PHPStan)
- `composer test` (Pest)
- `npm run test:e2e` (Playwright)

## 4. Security Plugins & Monitoring

In production, run one WordPress security plugin (e.g. Wordfence / Sucuri / iThemes Security) to provide:

- Login rate limiting and lockouts.
- File integrity monitoring.
- Basic malware signature scanning.
- Alerting on critical events (admin changes, plugin changes, etc.).

Application and web server logs should be:

- Retained for a reasonable period.
- Periodically reviewed for suspicious patterns.

## 5. Reverse Proxy / CDN

This project is reverse-proxy agnostic:

- Docker and WordPress do not depend on Cloudflare or any specific CDN.
- The application is accessed via nginx on port 80/443.
- A reverse proxy (such as Cloudflare) can be enabled or disabled at the DNS / hosting layer without changing containers.

When a reverse proxy is used:

- `WP_URL` must be set to the canonical HTTPS URL.
- (Optional) nginx may be configured to use `X-Forwarded-For` / `CF-Connecting-IP` to restore the real client IP.
- The host firewall should restrict origin access to only the proxy IP ranges.

## 6. Backups & Disaster Recovery

Minimum expectations:

- Regular database backups for `db_data`.
- Regular backups of `wp-content/uploads`.
- Configuration files and code stored in git and rebuilt via Docker.

Restore runbook:

1. Provision new VPS with Docker.
2. Clone this repository.
3. Restore database and uploads.
4. Set environment variables for the target environment (dev/staging/prod).
5. `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d`.

