# DLme Marketplace Starter

Dockerized WordPress / WooCommerce / Bookings multi-vendor consulting marketplace starter with:

- Docker & Docker Compose for dev and prod
- WP-CLI for programmatic WordPress setup
- Makefile for lifecycle commands
- Composer for PHP tooling (PHPStan, PHPCS, Pest)
- Playwright for headless E2E tests
- Basic observability hooks (X-Request-ID propagated from nginx to PHP)
- Cloudflare-agnostic, lift-and-shift friendly design

## Quick start (dev)

1. Copy env file:

   ```bash
   cp .env.example .env
   # edit passwords, admin email, WP_URL, etc.
   ```

2. Start and bootstrap dev stack:

   ```bash
   make dev-bootstrap
   ```

   This will:
   - Start containers
   - Download WordPress
   - Create `wp-config.php`
   - Install WordPress
   - Install plugins from `config/wp-plugins-wporg.txt`
   - (Optionally) install WooCommerce Bookings from a local zip

3. Visit the site:

   - http://localhost:8080

## Useful commands

- `make dev-up` / `make dev-down` – start/stop dev stack
- `make dev-destroy` – stop containers, delete volumes, wipe `src/`
- `make wp CMD="plugin list"` – run WP-CLI commands inside wpcli container
- `make lint` – PHPCS
- `make analyse` – PHPStan
- `make test` – Pest tests
- `npm run test:e2e` – Playwright E2E tests

## Environments & Databases

- Dev:
  - Uses `docker-compose.yml` with DB container `db`.
  - `.env` controls `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `WP_ENV=development`.

- Staging:
  - Same codebase, deployed on a staging server.
  - Staging server has its own `.env` (not committed) with:
    - Staging DB credentials (`DB_*`)
    - `WP_URL=https://staging.example.com`
    - `WP_ENV=staging`
  - Optionally uses an external managed DB instead of the compose `db` service.

- Production:
  - Same image as staging, different `.env`:
    - Prod DB credentials
    - `WP_URL=https://your-domain.com`
    - `WP_ENV=production`

## Production (local simulation)

Build and run with prod overrides:

```bash
make prod-build
make prod-up
```

In real staging/prod, CI should build and push the Docker image
(see `docker/php/Dockerfile.prod`) to a registry (e.g. GHCR), and
the server should pull and run it with `docker-compose.prod.yml`.

