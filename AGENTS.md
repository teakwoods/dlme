
# Your priorities are: **TDD, safety, correctness, and maintainability.**

---

## 0. Project Context

- **Project:** DLme Marketplace
- **Goal:** WordPress-based consulting marketplace using:
  - WordPress
  - WooCommerce
  - WooCommerce Bookings (premium, installed via zip)
  - A custom plugin: `dlme-marketplace`
- **Tooling:**
  - PHP 8.2
  - Composer
  - Pest (tests)
  - PHPStan (static analysis)
  - PHPCS + WPCS (coding standards)
  - Playwright (E2E tests)
  - Docker + docker-compose
  - Makefile with common lifecycle targets

You **can** run Docker, Composer, Node, and tests locally.  
Assume that CI may not yet exist or may be incomplete, so local commands are the source of truth.

---

## 1. Architecture & Key Directories

You should assume the structure is **roughly**:

- `docker-compose.yml` / `docker-compose.prod.yml`  
  Containers for: `db`, `wordpress` (php-fpm), `nginx`, `wpcli`.

- `docker/`  
  - `docker/php/Dockerfile` (dev image)
  - `docker/php/Dockerfile.prod` (prod image)
  - `docker/nginx/default.conf`
  - `docker/wp-cli/Dockerfile`

- `src/`  
  WordPress root (core + plugins + themes), bind-mounted in dev, copied in prod.

  - `src/wp-content/plugins/dlme-marketplace/`  
    → **Your main playground.** This is where DLme-specific functionality lives.
  - `src/wp-content/mu-plugins/`  
    → Must-use plugins for observability/logging (`REQUEST_ID`, JSON logging, etc.).
  - `src/wp-content/plugins-zips/`  
    → Local zips for premium plugins (e.g. WooCommerce Bookings).

- `config/wp-plugins-wporg.txt`  
  Manifest of WordPress.org plugins to install via WP-CLI (`woocommerce`, `dokan-lite`, `wordfence`, `two-factor`, etc.).

- `scripts/install-wporg-plugins.sh`  
  Script used by `wpcli` to install + activate plugins from the manifest.

- `composer.json` / `composer.lock`  
  PHP dependencies + scripts.

- `tests/`  
  - Pest tests (PHP unit/feature tests)
  - Test bootstrap

- `tests/e2e/`  
  Playwright E2E tests (TypeScript).

- `Makefile`  
  Wrapper around Docker/WP-CLI/tooling.

Unless explicitly asked by the human, **you should primarily modify**:

- `src/wp-content/plugins/dlme-marketplace/**`
- `tests/**`
- Documentation files (`AGENTS.MD`, `README.md`, etc.)

---

## 2. Core Philosophy: TDD + Small, Safe Changes

You should follow a **Test-Driven Development (TDD-ish) workflow**:

1. **Understand the change request**
   - Clarify intent from the human’s instructions and existing code.
   - Locate relevant classes/modules in `dlme-marketplace` and `tests`.

2. **Design behavior & write tests FIRST**
   - Add or update **Pest tests** that express the desired behavior.
   - Focus on **unit tests** that:
     - Don’t require WordPress to fully boot.
     - Operate on your own classes/services where possible.

3. **Run tests and observe the failure**
   - Use the provided commands (see below).
   - Confirm tests fail **for the expected reason** before changing production code.

4. **Implement the minimum code to pass tests**
   - Make small, focused changes in `dlme-marketplace` plugin code.
   - Prefer pure functions & small methods where possible.

5. **Refactor with safety**
   - After tests are green, refactor for clarity and DRYness.
   - Re-run tests to ensure behavior is unchanged.

6. **Run the full local test suite before “finishing”**
   - At minimum:
     - Static analysis (PHPStan)
     - Lint (PHPCS)
     - PHP tests (Pest)
   - Optionally:
     - Playwright E2E (if the human indicates they are ready / environment is running).

---

## 3. How to Run Things (Commands You Should Assume)

You should assume the following commands are available and recommended:

### 3.1. Spin up dev stack

First time (or after `dev-destroy`):

```bash
make dev-bootstrap
```

Afterwards, for normal development:

```bash
make dev-up      # start containers
make dev-down    # stop containers
```

### 3.2. Run PHP tooling (inside Docker)

These commands are executed from the **host** shell, but Docker runs the actual work.

- **Lint (PHPCS):**

  ```bash
  make lint
  ```

- **Static analysis (PHPStan):**

  ```bash
  make analyse
  ```

- **Tests (Pest):**

  ```bash
  make test
  ```

If you need to run a specific Composer script in the `wordpress` container:

```bash
docker compose --profile dev run --rm wordpress composer <command>
```

### 3.3. Run E2E tests (Playwright)

If the human indicates the stack is up and ready:

```bash
make ui-test
```

This will:

- Start the dev stack (if not already running).
- Wait briefly.
- Run `npm run test:e2e`.
- Tear down the stack.

**Do not assume** E2E must always be run; defer to the human for when it’s appropriate.

---

## 4. Testing Strategy (PHP)

### 4.1. Prefer unit tests where possible

- New behavior should be covered by **Pest tests** in `tests/`.
- Aim for tests that depend only on:
  - Your classes (e.g. `DLme\Core\VisibilityService`).
  - Simple PHP constructs, not full WordPress bootstrapping, when feasible.

**Patterns:**

- For new services:
  - Create `src/wp-content/plugins/dlme-marketplace/src/<Domain>/MyService.php`
  - Add tests under `tests/<Domain>/MyServiceTest.php` (or similar).
- Use Pest’s concise syntax for readability.

### 4.2. Integration vs unit

If you must interact with WordPress/WooCommerce/Dokan:

- Prefer to isolate that logic in a **thin “Integration” layer**, e.g.:

  - `src/wp-content/plugins/dlme-marketplace/src/Integration/WooCommerceHooks.php`
  - `src/wp-content/plugins/dlme-marketplace/src/Integration/VendorPluginHooks.php`

- Unit-test the **core logic** behind a stable interface:

  - e.g. `VisibilityService::isUserAuthorizedForVendor($userId, $vendorId, $visibilityMode)`

- Keep WordPress-specific calls (`get_user_meta`, `add_action`, etc.) **at the edges** so most logic is testable without booting WP.

### 4.3. Static analysis is part of “done”

Before you treat a change as complete, your code should:

- **Pass PHPStan** at the configured level (see `phpstan.neon.dist`).
- **Pass PHPCS** using the configured ruleset (`phpcs.xml`):
  - PSR-12 + WordPress-Core/Docs/Extra.

If you must add `ignoreErrors` entries for PHPStan, do so **sparingly** and explain why in a comment.

---

## 5. PHP & WordPress Best Practices

### 5.1. Coding style

- Follow PSR-12 & WordPress coding standards.
- Use strict types when possible:

  ```php
  declare(strict_types=1);
  ```

- Prefer small, focused classes and methods.

### 5.2. Security

When working with WordPress/WooCommerce:

- **Escape output**:
  - `esc_html()`, `esc_attr()`, `esc_url()`, etc., in templates/output.
- **Sanitize input**:
  - `sanitize_text_field()`, `sanitize_email()`, etc.
- **Use nonces & capabilities** for any state-changing operations:
  - `current_user_can( 'some_cap' )`
  - `wp_verify_nonce( ...)`

Never:

- Trust `$_POST` / `$_GET` / `$_REQUEST` directly.
- Expose sensitive data in logs, error messages, or front-end HTML.

### 5.3. Hooks & filters

When you add new WordPress hooks:

- Use descriptive callback function/method names.
- Keep callback bodies short; delegate to a service class where possible.
- Document the behavior clearly in PHPDoc *and* tests.

Example pattern:

```php
add_filter( 'woocommerce_get_price_html', [ $this, 'filterPriceHtml' ], 10, 2 );

/**
 * Optionally hide or alter price display based on vendor visibility rules.
 *
 * @param string        $price_html Generated price HTML.
 * @param WC_Product    $product    WooCommerce product.
 * @return string
 */
public function filterPriceHtml( string $price_html, WC_Product $product ): string {
    // ...
}
```

---

## 6. Documentation Expectations

For any non-trivial change, you should:

1. **Update or add PHPDoc blocks** for:
   - Public classes
   - Public methods
   - Functions in global scope

2. **Explain “why” when behavior is non-obvious**
   - Add short inline comments **only** when the intent is not self-evident.
   - Prefer small, well-named methods over long comment blocks.

3. **Update high-level docs when appropriate:**
   - If you add a new subsystem or major feature in `dlme-marketplace`, consider:
     - Adding a short section to `README.md` or a dedicated `docs/` file.
     - Updating `SECURITY.md` if you affect security posture (auth, permissions, data handling).

4. **Keep AGENTS.MD in sync**
   - If you add new testing or build steps that agents should follow, update **this AGENTS.MD**.
   - Always maintain **a single source of truth** for agent-facing instructions.

---

## 7. Boundaries: What You Should *Not* Change Without Explicit Instruction

To avoid destabilizing the environment, **do not modify** the following unless the human explicitly asks:

- Core Docker config:
  - `docker-compose.yml`
  - `docker-compose.prod.yml`
  - `docker/php/Dockerfile*`
  - `docker/nginx/default.conf`
  - `docker/wp-cli/Dockerfile`
- The structure of the WordPress root (`src/`) beyond:
  - `dlme-marketplace` plugin code
  - The test and documentation files
- CI workflows under `.github/workflows`, unless you’re specifically asked to do infra work.
- `.env` files or any secret material (never commit secrets; assume they are `.gitignore`’d).

When in doubt, **prefer to implement features inside**:

- `src/wp-content/plugins/dlme-marketplace/**`
- `tests/**`

and **coordinate with the human** for infra-level changes.

---

## 8. Workflow with the Human Developer

1. **Clarify the task**
   - Restate your understanding of the requested behavior.
   - Identify the impacted components (services, hooks, tests).

2. **Propose a plan (briefly)**
   - Outline classes/files you intend to touch.
   - Describe the tests you’ll add or modify.

3. **Implement in small steps**
   - Add failing tests → run tests → implement → re-run.
   - Share diff or summary of key changes.

4. **Surface issues early**
   - If you suspect something requires:
     - New Docker config,
     - Deep WordPress bootstrapping,
     - Plugin (Woo/Dokan) behavior that you can’t fully validate,
   - Flag this to the human clearly and suggest options.

5. **Respect “green bar” discipline**
   - Don’t leave broken tests or unchecked static analysis.
   - If something *must* be broken (e.g. mid-refactor), clearly label it as WIP.

---

## 9. Summary Checklist (Before You Say “Done”)

For each change you make, you should be able to answer “yes” to:

- [ ] Have I written or updated tests that cover the new behavior?
- [ ] Do the tests pass locally?
- [ ] Does `make lint` pass?
- [ ] Does `make analyse` pass?
- [ ] Is the new code located in appropriate places (mostly `dlme-marketplace`)?
- [ ] Are public methods/classes documented with PHPDoc where appropriate?
- [ ] Did I avoid touching Docker / infra / secrets without explicit instruction?
- [ ] Have I left clear breadcrumbs (tests, docs) so future agents/humans understand the change?

If the answer to any is “no”, treat the change as **incomplete** and keep iterating.

---
