# Handoff: Seller Availability Core Implementation

**Date**: 2025-11-17
**Branch**: `claude/summarize-wordpress-scaffold-01WS8BbnjZer8gAjv8UeRu57`
**Commit**: `910826d`

---

## Specification Summary

Implemented the **Seller Availability** feature for the DLme marketplace, focusing exclusively on **framework-agnostic domain logic** as defined in the availability spec.

### Scope: What I Built

**Core Domain Concepts**:
- Manual "Available Now" flag (boolean toggle per seller)
- Weekly schedules with day-of-week and hour ranges
- Per-seller timezone support with PST fallback
- Pure PHP representations with full validation

**v1 Behavior**:
- `isSellerAvailableNow()` returns `true` only if manual flag is `true`
- Schedules are validated and stored but NOT used for gating in v1
- `isWithinScheduledHours()` is implemented and tested for future use

### Scope: What I Did NOT Build

Per CLAUDE.md constraints, I did **not** touch:

- WordPress/WooCommerce/Dokan integration
- Plugin bootstrap or hook wiring (`add_action`, `add_filter`)
- Database layer (no `get_user_meta` calls)
- UI components or admin dashboards
- REST API endpoints
- Docker/nginx/CI configuration
- E2E tests

Those are the responsibility of the **full-scope agent/human**.

---

## Implementation Details

### File Structure

```
src/wp-content/plugins/dlme-marketplace/src/Core/
├── AvailabilityService.php              # Main service
├── AvailabilityStatus.php               # Enum: available_now | offline
├── DayOfWeek.php                        # Enum: mon-sun
├── TimeRange.php                        # Value object (0-23, 1-24)
├── SellerSchedule.php                   # Value object (weekly schedule)
├── SellerAvailabilityRepository.php     # Interface (persistence contract)
├── TimezoneProvider.php                 # Interface (timezone resolution)
├── InMemorySellerAvailabilityRepository.php  # Test implementation
├── InMemoryTimezoneProvider.php         # Test implementation
├── DomainException.php                  # Base exception
├── InvalidTimeRangeException.php        # Time range validation errors
└── InvalidScheduleException.php         # Schedule validation errors

tests/Core/
├── AvailabilityServiceTest.php          # 19 tests
├── SellerScheduleTest.php               # 10 tests
└── TimeRangeTest.php                    # 14 tests
```

### Key Classes

#### 1. `AvailabilityService`

**Constructor**:
```php
public function __construct(
    SellerAvailabilityRepository $repository,
    TimezoneProvider $timezoneProvider
);
```

**Public API**:
```php
// v1: Only checks manual flag
public function isSellerAvailableNow(int $sellerId): bool

// Returns enum status
public function getAvailabilityStatus(int $sellerId): AvailabilityStatus

// Schedule management
public function getSchedule(int $sellerId): SellerSchedule
public function saveSchedule(int $sellerId, SellerSchedule $schedule): void

// Manual flag control
public function setAvailableNow(int $sellerId, bool $available): void

// Schedule checking (implemented but not used for gating in v1)
public function isWithinScheduledHours(int $sellerId, DateTimeInterface $localDateTime): bool
```

#### 2. `SellerSchedule`

**Creation & Validation**:
```php
// Validates, normalizes, and sorts
SellerSchedule::create([
    'mon' => [
        ['start' => 9, 'end' => 12],
        ['start' => 13, 'end' => 17],
    ],
    // ... other days optional
]);
```

**Normalization includes**:
- All 7 days present (empty arrays for unused days)
- Ranges sorted by start time
- Overlap detection (throws `InvalidScheduleException`)
- Hour validation (start: 0-23, end: 1-24, start < end)

#### 3. `TimeRange`

**Creation**:
```php
TimeRange::create(9, 17);  // 9am-5pm
```

**Validation**:
- `start`: 0-23 (hour when range begins)
- `end`: 1-24 (hour when range ends, exclusive)
- Constraint: `start < end`
- Throws `InvalidTimeRangeException` on invalid input

---

## Test Coverage

**43 tests, 97 assertions** – all passing ✅

### Manual Flag Behavior (5 tests)
- ✓ Available when flag is true
- ✓ Offline when flag is false
- ✓ Offline by default
- ✓ Can set flag to true
- ✓ Can set flag to false

### Schedule Validation (7 tests)
- ✓ Accepts valid schedule
- ✓ Rejects overlapping ranges
- ✓ Rejects out-of-bounds start hour
- ✓ Rejects out-of-bounds end hour
- ✓ Rejects start >= end
- ✓ Normalizes missing days
- ✓ Sorts ranges by start time

### isWithinScheduledHours (7 tests)
- ✓ Returns true when inside schedule
- ✓ Returns false when outside hours
- ✓ Returns false when outside day
- ✓ Returns false for empty schedule
- ✓ Handles timezone conversion (UTC ↔ PST ↔ Berlin)
- ✓ Checks multiple ranges within a day
- ✓ Uses default timezone (PST) when seller has none

### Value Objects (24 tests)
- TimeRange: validation, contains, overlaps, toArray
- SellerSchedule: creation, normalization, validation, queries

---

## Code Quality

- ✅ **PHP 8.2** with native enums
- ✅ **Strict types** (`declare(strict_types=1)`) throughout
- ✅ **PSR-12** formatting
- ✅ **PHPStan level 6** compliant
- ✅ **Full type hints** on all methods
- ✅ **Zero WordPress dependencies** in Core
- ✅ **Dependency injection** for all external concerns
- ✅ **Immutable value objects** (TimeRange, SellerSchedule)

---

## What's Next: Integration Requirements

The full-scope agent/human needs to implement the **integration layer**:

### 1. WordPress Repository Implementation

Create `src/wp-content/plugins/dlme-marketplace/src/Integration/WpSellerAvailabilityRepository.php`:

```php
final class WpSellerAvailabilityRepository implements SellerAvailabilityRepository
{
    public function getAvailableNowFlag(int $sellerId): bool
    {
        return (bool) get_user_meta($sellerId, 'dlme_available_now', true);
    }

    public function setAvailableNowFlag(int $sellerId, bool $available): void
    {
        update_user_meta($sellerId, 'dlme_available_now', $available);
    }

    public function getSchedule(int $sellerId): SellerSchedule
    {
        $rawSchedule = get_user_meta($sellerId, 'dlme_schedule', true);
        $scheduleArray = $rawSchedule ? json_decode($rawSchedule, true) : [];
        return SellerSchedule::create($scheduleArray ?: []);
    }

    public function saveSchedule(int $sellerId, SellerSchedule $schedule): void
    {
        update_user_meta(
            $sellerId,
            'dlme_schedule',
            wp_json_encode($schedule->toArray())
        );
    }

    public function getTimezone(int $sellerId): ?string
    {
        return get_user_meta($sellerId, 'dlme_timezone', true) ?: null;
    }

    public function saveTimezone(int $sellerId, ?string $timezone): void
    {
        if ($timezone === null) {
            delete_user_meta($sellerId, 'dlme_timezone');
        } else {
            update_user_meta($sellerId, 'dlme_timezone', $timezone);
        }
    }
}
```

### 2. Production Timezone Provider

Create `src/wp-content/plugins/dlme-marketplace/src/Integration/WpTimezoneProvider.php`:

```php
final class WpTimezoneProvider implements TimezoneProvider
{
    public function __construct(
        private SellerAvailabilityRepository $repository
    ) {}

    public function getSellerTimezone(int $sellerId): \DateTimeZone
    {
        $timezone = $this->repository->getTimezone($sellerId);

        if (!$timezone) {
            // Fallback to site timezone or PST
            $timezone = get_option('timezone_string') ?: 'America/Los_Angeles';
        }

        return new \DateTimeZone($timezone);
    }
}
```

### 3. Service Container / Dependency Injection

Wire up the service in plugin bootstrap:

```php
// In dlme-marketplace.php or a service provider class

$repository = new WpSellerAvailabilityRepository();
$timezoneProvider = new WpTimezoneProvider($repository);
$availabilityService = new AvailabilityService($repository, $timezoneProvider);

// Make available globally (via container, registry, or DI)
```

### 4. REST API Endpoints

Expose availability operations:

- `GET /wp-json/dlme/v1/sellers/{id}/availability` - Get status + schedule
- `POST /wp-json/dlme/v1/sellers/{id}/available-now` - Toggle flag
- `PUT /wp-json/dlme/v1/sellers/{id}/schedule` - Update schedule
- `GET /wp-json/dlme/v1/sellers/{id}/timezone` - Get timezone
- `PUT /wp-json/dlme/v1/sellers/{id}/timezone` - Set timezone

### 5. Admin Dashboard UI

Build seller dashboard page:

- Toggle "I'm Available Now" switch
- Weekly schedule editor (day x time ranges)
- Timezone selector
- Live preview of current status

### 6. Buyer-Facing Display

Show availability on seller profile:

- Badge: "Available Now" vs "Offline"
- Display scheduled hours (if desired)
- Time zone indicator

### 7. Future: Schedule-Based Gating (v2)

When ready to enforce schedules:

```php
public function isSellerAvailableNow(int $sellerId): bool
{
    $manualFlag = $this->repository->getAvailableNowFlag($sellerId);

    // v2: Also check schedule
    if ($manualFlag) {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $this->isWithinScheduledHours($sellerId, $now);
    }

    return false;
}
```

### 8. E2E Tests

Create Playwright tests for:

- Seller toggling "Available Now"
- Seller editing weekly schedule
- Buyer seeing availability badge
- Validation error handling in UI

---

## Testing the Core

To run tests locally (requires Docker):

```bash
# Via Make (uses Docker)
make test

# Or directly (if composer deps installed)
vendor/bin/pest

# Static analysis
vendor/bin/phpstan analyse
```

All tests are **pure PHP** with no WordPress runtime needed.

---

## Important Notes

### Timezone Handling

- Seller schedules are interpreted in **seller's timezone**
- Default fallback is **America/Los_Angeles** (PST/PDT)
- `isWithinScheduledHours()` correctly converts input datetime to seller timezone

### Validation Rules

**TimeRange**:
- Start: 0-23 (inclusive)
- End: 1-24 (exclusive boundary)
- Must have `start < end`

**SellerSchedule**:
- All 7 days must be present (can be empty arrays)
- Ranges auto-sorted by start time
- Overlapping ranges within a day are **rejected**
- Adjacent ranges (e.g., 9-12 and 12-15) are **allowed**

### v1 vs v2 Behavior

**v1 (Current)**:
- Manual flag **only** determines availability
- Schedules stored but **not enforced**

**v2 (Future)**:
- Manual flag **AND** schedule both required
- Seller must be within scheduled hours to be "available now"

The core is built to support both seamlessly by changing `isSellerAvailableNow()` logic.

---

## Questions?

If you encounter issues with the core logic:

1. Check test files for expected behavior examples
2. Review class PHPDoc for parameter/return types
3. Run tests to verify core behavior hasn't regressed
4. See `docs/specs/availability.md` for original spec (if exists)

For integration questions, the interfaces define clear contracts:
- `SellerAvailabilityRepository` - What data to persist and retrieve
- `TimezoneProvider` - How to resolve seller timezones
- `AvailabilityService` - Public API for all availability operations

---

## Commit Summary

**Files Changed**: 16
**Lines Added**: 1,126
**Lines Removed**: 2

**Commit Message**:
```
Implement core seller availability domain logic

Add framework-agnostic availability core with TDD approach:
- Core value objects & enums (DayOfWeek, AvailabilityStatus, TimeRange, SellerSchedule)
- Domain exceptions (InvalidTimeRangeException, InvalidScheduleException)
- Repository & provider interfaces
- In-memory test implementations
- AvailabilityService with full validation and schedule checking
- 43 comprehensive Pest tests (all passing)
- Strict types, PSR-12, PHPStan level 6 compliant
```

**Branch**: `claude/summarize-wordpress-scaffold-01WS8BbnjZer8gAjv8UeRu57`
**Status**: Pushed to remote ✅

---

**End of Handoff**
