# Load env vars from .env if present
ifneq (,$(wildcard ./.env))
    include .env
    export
endif

.PHONY: help
help:
	@echo "Common commands:"
	@echo "  make dev-up         - Start dev stack (with wpcli, bind mounts)"
	@echo "  make dev-down       - Stop dev stack"
	@echo "  make dev-bootstrap  - Bootstrap WordPress + plugins in dev"
	@echo "  make wp CMD=...     - Run WP-CLI (dev only)"
	@echo "  make prod-build     - Build production image locally"
	@echo "  make prod-up        - Bring up prod stack locally"
	@echo "  make prod-down      - Stop prod stack locally"
	@echo "  make lint           - Run PHPCS lint"
	@echo "  make analyse        - Run PHPStan analysis"
	@echo "  make test           - Run PHP tests (Pest)"
	@echo "  make ui-test        - Run Playwright E2E tests (dev stack)"

###############################################
# DEV TARGETS
###############################################

.PHONY: dev-up
dev-up:
	docker compose --profile dev up -d

.PHONY: dev-down
dev-down:
	docker compose --profile dev down

.PHONY: wp
wp:
	docker compose --profile dev run --rm wpcli $(CMD)

.PHONY: dev-bootstrap
dev-bootstrap: dev-up dev-wait-for-db dev-wp-core-download dev-wp-config dev-wp-core-install dev-wp-plugins

.PHONY: dev-wait-for-db
dev-wait-for-db:
	@echo "Waiting for database (dev)..."
	sleep 10

.PHONY: dev-wp-core-download
dev-wp-core-download:
	@if [ ! -f "src/wp-settings.php" ]; then \
		echo "Downloading WordPress core (dev)..."; \
		docker compose --profile dev run --rm wpcli core download --path=/var/www/html; \
	else \
		echo "WordPress core already present, skipping download."; \
	fi

.PHONY: dev-wp-config
dev-wp-config:
	@if [ ! -f "src/wp-config.php" ]; then \
		echo "Creating wp-config.php (dev)..."; \
		docker compose --profile dev run --rm wpcli config create \
			--path=/var/www/html \
			--dbname="$$DB_NAME" \
			--dbuser="$$DB_USER" \
			--dbpass="$$DB_PASSWORD" \
			--dbhost="db:3306" \
			--skip-check; \
	else \
		echo "wp-config.php already exists, skipping."; \
	fi

.PHONY: dev-wp-core-install
dev-wp-core-install:
	@echo "Checking if WordPress is installed (dev)..."
	@if ! docker compose --profile dev run --rm wpcli core is-installed; then \
		echo "Installing WordPress (dev)..."; \
		docker compose --profile dev run --rm wpcli core install \
			--url="$$WP_URL" \
			--title="$$WP_TITLE" \
			--admin_user="$$WP_ADMIN_USER" \
			--admin_password="$$WP_ADMIN_PASS" \
			--admin_email="$$WP_ADMIN_EMAIL"; \
	else \
		echo "WordPress already installed, skipping."; \
	fi

.PHONY: dev-wp-plugins
dev-wp-plugins:
	@echo "Installing WordPress.org plugins from manifest (dev)..."
	docker compose --profile dev run --rm --entrypoint bash wpcli -lc "scripts/install-wporg-plugins.sh"

	@echo "Checking for WooCommerce Bookings zip..."
	@if [ -f "src/wp-content/plugins-zips/woocommerce-bookings.zip" ]; then \
		docker compose --profile dev run --rm wpcli plugin install \
			/var/www/html/wp-content/plugins-zips/woocommerce-bookings.zip \
			--activate; \
	else \
		echo "NOTE: WooCommerce Bookings zip not found at src/wp-content/plugins-zips/woocommerce-bookings.zip"; \
		echo "      Place the premium plugin zip there if you own it."; \
	fi

.PHONY: dev-destroy
dev-destroy:
	@echo "Stopping dev containers and removing volumes..."
	docker compose --profile dev down -v
	@echo "Removing WordPress src directory (dev)..."
	rm -rf src/*
	@echo "Done. Run 'make dev-bootstrap' to rebuild from scratch."

###############################################
# PROD TARGETS (LOCAL ONLY)
###############################################

.PHONY: prod-build
prod-build:
	docker build -f docker/php/Dockerfile.prod -t dlme-local:latest .

.PHONY: prod-up
prod-up:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

.PHONY: prod-down
prod-down:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml down

###############################################
# TOOLING
###############################################

.PHONY: lint
lint:
	docker compose --profile dev run --rm wordpress composer lint

.PHONY: analyse
analyse:
	docker compose --profile dev run --rm wordpress composer analyse

.PHONY: test
test:
	docker compose --profile dev run --rm wordpress composer test

.PHONY: ui-test
ui-test:
	docker compose --profile dev up -d
	sleep 15
	npm run test:e2e || (docker compose --profile dev down -v && exit 1)
	docker compose --profile dev down -v
