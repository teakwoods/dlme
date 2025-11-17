

## 0. Canonical Documents

Treat these as your **contract**:

- `CLAUDE.md` (this file) – overrides any conflicting instructions elsewhere for you.
- `AGENTS.MD` – general practices (only where consistent with this file).
- Feature specs under `docs/specs/*.md` (e.g. `docs/specs/availability.md`).

If there is a conflict, this order wins:

1. `AGENT_SANDBOX.md`
2. `docs/specs/<feature>.md`
3. `AGENTS.MD`

---

## 1. Scope – What You **May Modify**

You are allowed to:

- Core PHP logic:
  - `src/wp-content/plugins/dlme-marketplace/src/Core/**`
  - New value objects, interfaces, service classes under `DLme\Core\...`.

- Test code:
  - `tests/**` (unit tests, ideally organized under similar namespaces/dirs).
  - Test bootstrap files if you need minor adjustments.

- Documentation:
  - `docs/specs/**/*.md` – to clarify behavior when asked.
  - `AGENTS.MD` / `AGENT_SANDBOX.md` – to keep guidelines in sync with reality (carefully, and always improving clarity).

**You must NOT modify**:

- Docker, nginx, or CI:
  - `docker-compose*.yml`
  - Any `Dockerfile`
  - `docker/nginx/*.conf`
  - `.github/workflows/**`

- Environment files and secrets:
  - `.env`, `.env.example`

- WordPress runtime glue:
  - Plugin bootstrap files (e.g. `dlme-marketplace.php`) unless explicitly asked.
  - Theme/template files.
  - Any code that calls `add_action`, `add_filter`, or depends on `get_user_meta()` etc. **directly**.
    - Those belong in integration layers for the full-scope agent/human.

---

## 2. Design Philosophy – Core, Pure, Testable

You build **framework-agnostic domain logic**. That means:

- Prefer pure PHP services that:
  - Accept primitive types / value objects.
  - Return value objects / primitives.
  - Have no direct dependency on WordPress, WooCommerce, or global state.

- Use abstractions/interfaces for external concerns:
  - Example: `SellerMetaRepository` interface for per-seller data.
  - You implement an **in-memory** version for tests:
    - `InMemorySellerMetaRepository` used only in tests.
  - Full-scope agent/human later implements `WpSellerMetaRepository` or similar.

This lets you fully test behavior in your sandbox without needing WordPress.

---

## 3. TDD and “Self-Healing” Workflow

For each feature you work on:

1. **Read the spec**
   - Start with `docs/specs/<feature>.md`.
   - If anything is ambiguous, add clarifying notes in the spec (markdown comments) rather than guessing wildly.

2. **Define or update tests FIRST**
   - Create/extend tests in `tests/Core/...` that express the behavior from the spec.
   - Tests should be **pure PHP**:
     - Do not rely on running WordPress or a DB.
     - Use in-memory implementations or fakes for dependencies.
   - Example for availability:
     - Test that `AvailabilityService::isSellerAvailableNow()` returns true/false for various schedules and times.

3. **Run tests in your environment**
   - If you can execute tests:
     - Run Pest (or PHPUnit) against the relevant files.
   - If you cannot execute, reason as if you ran them:
     - Ensure assertions are logically correct.
     - Avoid tests that rely on undefined classes/functions.

4. **Implement the minimum code to satisfy tests**
   - Implement or update classes under `DLme\Core\...`.
   - Use dependency injection and interfaces for infrastructure boundaries.

5. **Self-heal using test/static-analysis feedback**
   - If tests or static analysis fail (e.g., error logs provided by CI or the human):
     - Update code to address root causes.
     - Never “fix” tests just to match buggy behavior unless the spec explicitly changed.

6. **Keep behavior and spec in sync**
   - If you discover the spec is incomplete or conflicting, propose edits to `docs/specs/<feature>.md`.
   - Update tests and core code **together** to reflect the clarified behavior.

---

## 4. Testing Guidelines

### 4.1. Structure

- Use Pest for test files:
  - Put tests under `tests/Core/...` mirroring the namespace structure, e.g.:
    - Class: `DLme\Core\AvailabilityService`
    - Tests: `tests/Core/AvailabilityServiceTest.php`

- Tests should:
  - Be deterministic.
  - Avoid relying on global state.
  - Prefer constructor injection or method parameters for everything needed.

### 4.2. What tests should cover

For each core service:

- **Happy paths**:
  - Main use cases described in the spec.
- **Edge cases**:
  - Missing or malformed input (e.g., empty schedules, invalid JSON).
  - Time zone fallbacks if `dlme_timezone` is absent.
- **Regression cases**:
  - Any behavior that previously broke and was fixed should get a dedicated test.

### 4.3. No runtime dependencies

Your tests must **not**:

- Call WordPress functions (`get_user_meta`, `add_action`, etc.).
- Expect a database connection.
- Hit HTTP requests or file system paths outside the repo.

If you need to model external behavior, create fakes/mocks in pure PHP.

---

## 5. Static Analysis & Style

You don’t run tools like PHPStan/PHPCS yourself in the sandbox, but your code must be written to **pass them when run by CI or the human**.

Follow these rules:

- **Types and signatures**
  - Use `declare(strict_types=1);` at the top of each PHP file.
  - Type-hint parameters and return types wherever possible.
  - Prefer value objects over loose arrays when complexity grows.

- **PHPDoc**
  - Document public classes and methods with PHPDoc blocks where the signature alone isn’t obvious.
  - Keep PHPDoc in sync with actual behavior.

- **Coding standards**
  - Follow PSR-12 formatting.
  - Follow WordPress naming and escaping patterns where relevant to domain logic.

If CI or the human provides you with PHPStan/PHPCS error logs:

1. Read and understand the error.
2. Adjust code or annotations to fix the root issue.
3. Avoid blanket ignores; use targeted ignores only when absolutely necessary and document why.

---

## 6. Coordination with the Full-Scope Agent

Your output is used by a full-scope agent/human who:

- Wires your core logic into WordPress/WooCommerce.
- Builds seller dashboards, buyer flows, and E2E tests.

Your responsibilities relative to them:

- Provide **clear interfaces** and **well-tested behavior**.
- Keep **tests and specs** as the contract:
  - If behavior changes, tests and `docs/specs/...` must change too.
- Avoid making assumptions about infrastructure:
  - Do not call `get_user_meta` directly; let the full agent write adapters.
  - Do not embed HTML/templating in core classes.

---

## 7. Before You Consider a Change “Done”

For each change you make, ensure:

- [ ] The relevant `docs/specs/<feature>.md` accurately describes the behavior.
- [ ] There are tests covering all new/changed behavior.
- [ ] Tests are internally consistent and logically correct.
- [ ] Public interfaces are typed and documented.
- [ ] Code obeys strict types and is likely to pass PHPStan at the configured level.
- [ ] Code style follows PSR-12 / WPCS conventions.

If any box is unchecked, treat the change as **incomplete**.

---