# Agent Journal - Call Request Subsystem

## Session Date: 2025-01-19

## Context

This journal documents the design decisions and implementation plan for the **Call Request subsystem** - the post-purchase call scheduling, presence tracking, and callback logic for the DLme Marketplace.

This work follows the completion of:
1. Seller Availability System (availability flags, schedules, timezone support)
2. CTA Decision Logic (context-aware button configuration)
3. Checkout Sessions & Payment Flow (idempotent session management)
4. PSR-3 Structured Logging (event tracking throughout core services)

## Business Goal

**Problem**: Clients and consultants miss each other when consultant is busy or unavailable at the moment of purchase.

**Solution**: Post-purchase call scheduling system where:
- Client purchases consultation time (creates payment + call contract)
- If consultant busy → client can be "captured for later"
- Consultant chooses callback delay (5-20 min or after current session)
- System manages scheduling and coordinates with downstream calling system (Asterisk)
- Both parties get connected at scheduled time without manual follow-up

## Key Product Requirements

From "Smart Call Request & Callback - V0 Product Spec":

1. **Client purchases consultation** → creates call contract with timing constraints
2. **Consultant can defer** → "Capture for Later" creates rescheduled contract
3. **Presence awareness** → system tracks if consultant is in active call
4. **Automatic callback** → scheduled contracts sent to Asterisk for execution
5. **Completion tracking** → Asterisk webhooks report call outcome (for PAYG billing)

## Domain Model Design Decisions

### 1. Naming: CallRequest vs CallContract

**Decision**: Use `CallRequest` as the entity name.

**Rationale**:
- Spec uses "Call Request" terminology consistently
- Represents the business entity: a snapshot/contract tying payment + product + timing
- "Request" emphasizes the client's intent to connect
- Status field tracks lifecycle (pending → scheduled → completed)

**Key insight from discussion**:
> "call contract and request are the same business entity... it's a contract because it 'freezes'/snapshots and ties the payment info, with the price the client clicked on"

### 2. CallRequest Fields (Complete Entity)

```php
CallRequest {
    // Identity & References
    string $id;                          // UUID with 'req_' prefix
    string $checkoutSessionId;           // Original payment session
    ?string $supersededByRequestId;      // If this was rescheduled
    ?string $supersededRequestId;        // If this supersedes an earlier one

    // Participants
    int $sellerId;                       // Consultant
    int $buyerId;                        // Client
    string $buyerPhone;
    ?string $buyerEmail;

    // Product/Pricing (frozen snapshot from SKU)
    int $productId;
    string $sku;
    PricingModel $pricingModel;          // PAYG or PREPAID_TIME
    string $agreedPrice;                 // e.g. "50.00"
    string $currency;
    ?int $prepaidMinutes;                // For prepaid model only

    // Timing Constraints (from product metadata)
    DateTimeImmutable $createdAt;
    DateTimeImmutable $initiationWindowStart;
    DateTimeImmutable $initiationWindowEnd;  // Call must start by this time
    int $callDurationMinutes;                // How long call is valid once started
    ?DateTimeImmutable $scheduledExecutionTime;  // For deferred contracts

    // Execution Tracking (populated by Asterisk callbacks)
    CallRequestStatus $status;
    ?DateTimeImmutable $actualCallStartTime;
    ?DateTimeImmutable $actualCallEndTime;
    ?int $actualCallDurationMinutes;     // For PAYG billing
    bool $callCompletedSuccessfully;

    // Metadata
    string $correlationId;
    ?string $referrerUrl;
}
```

### 3. Timing Windows - Two Separate Constraints

**Decision**: Track both initiation window AND call duration.

**Constraint 1 - Initiation Window**: "Call must START by this time"
- Example: "Must initiate within 15 minutes from purchase"
- Stored as: `initiationWindowStart` + `initiationWindowEnd`
- Comes from: Product metadata (WooCommerce SKU configuration)

**Constraint 2 - Call Duration**: "Once started, call is valid for X minutes"
- Example: "30 minutes of call time"
- Stored as: `callDurationMinutes`
- Comes from: Product metadata
- For PAYG: Actual duration tracked for billing

**Key decision from discussion**:
> "valid for 30 minutes as long as the call initiates in the next 15 from X timestamp"
> Q: "Is this correct - we need both fields?"
> A: "correct, need both"

### 4. Rescheduling - Creates New CallRequest

**Decision**: When consultant chooses "Capture for Later", create a NEW CallRequest entity.

**Behavior**:
1. Original CallRequest marked with `status = SUPERSEDED`
2. New CallRequest created with:
   - New `id`
   - Links back to original via `supersededRequestId`
   - Links back to same `checkoutSessionId`
   - Same `agreedPrice`, `sku`, `pricingModel`
   - NEW `initiationWindowEnd` = now + consultant's chosen delay
   - Same `callDurationMinutes` (unless PAYG, then recalculated)
3. Original CallRequest's `supersededByRequestId` points to new one

**Key decision from discussion**:
> "original contract is marked as superseded. we would create a new separate call contract and links back to original checkout session with the same price"

### 5. Rescheduling Time Calculation

**Decision**: Simple time recalculation - Option B (now + delay).

**For fixed delays** (consultant enters minutes: 5, 10, 20):
- `scheduledExecutionTime` = now + delay minutes
- `initiationWindowEnd` = scheduledExecutionTime + small buffer (e.g., +5 min for execution tolerance)
- `callDurationMinutes` = same as original (unless PAYG)

**For "After Current Session"**:
- Requires consultant to enter buffer (1, 2, or 10 minutes)
- Requires `estimated_session_end` from presence webhook
- `scheduledExecutionTime` = estimated_session_end + buffer
- If `estimated_session_end` missing → cannot use this option

**Key decision from discussion**:
> Q: "Original contract might have 'initiationWindowEnd': '15 minutes from purchase'. If consultant chooses '10 minute delay', the new contract should have...?"
> A: "Option B. call duration would be same unless it was PAYG"

### 6. Consultant Presence - External Webhook

**Decision**: Presence is NOT managed in our system. It arrives via webhook from external system.

**Webhook Payload** (what we receive):
```json
{
  "consultant_id": 123,
  "status": "in_call" | "idle" | "offline",
  "timestamp": "2025-01-19T15:30:00Z",
  "session_id": "ext_session_abc",
  "estimated_session_end": "2025-01-19T15:45:00Z",
  "current_call_request_id": "req_xyz789"
}
```

**Storage Strategy**: Custom database table (production: `wp_dlme_consultant_presence`)
- One row per consultant (upsert on webhook)
- Fast lookups for availability checks
- No complex state management in our code

**Key decision from discussion**:
> "I don't want to manage 'state' or active session tracking in the wordpress app, so Consultant presence is updated by a webhook"

### 7. Product Metadata - Interface for WooCommerce Integration

**Decision**: Define abstract interface for product configuration lookup.

```php
interface ProductMetadataProvider
{
    public function getPricingModel(string $sku): PricingModel;
    public function getCallDurationMinutes(string $sku): int;
    public function getInitiationWindowMinutes(string $sku): int;
    public function getPrepaidMinutes(string $sku): ?int;
}
```

**SANDBOX agent** (this implementation):
- Defines the interface
- Creates in-memory fake for tests

**Full-scope agent** (future):
- Implements `WooCommerceProductMetadataProvider`
- Reads from WC product custom fields/metadata
- Consultants configure via Dokan product editor

**Key decision from discussion**:
> "we need to figure out how to make this ALL this work with Woocommerce / Dokan/ or existing code base"
> "I'd like to take advantage of as much as possible but not square-peg/round-hole something"

### 8. CallRequest Storage Strategy

**Decision**: Custom database table (not WooCommerce order items or post types).

**Rationale**:
- CallRequests have complex lifecycle (rescheduling, superseding, execution tracking)
- Need optimized queries (find by status, find by scheduled time, etc.)
- High volume: one per consultation, multiple if rescheduled
- Not a good fit for WC Order Items (designed for products in cart)
- Post Types too heavy for transactional data

**Production Table** (for full-scope agent):
- `wp_dlme_call_requests` with all fields from entity
- Indexed on: status, scheduled_execution_time, consultant_id, checkout_session_id

**Key decision from discussion**:
> Q: "CallRequest storage - Option 1 (WC Order Items), Option 2 (Custom table), Option 3 (Custom Post Type)?"
> A: "Option 2"

### 9. CheckoutSession Extensions

**Decision**: Add buyer contact fields directly to CheckoutSession entity.

**New fields**:
```php
// In CheckoutSession class:
public ?string $buyerPhone = null;
public ?string $buyerEmail = null;
```

**Rationale**:
- Buyer contact is part of purchase flow (collected at checkout)
- CallRequest copies this data when created (frozen snapshot)
- No separate value object needed - simple scalar fields

**Key decision from discussion**:
> Q: "Should I add directly to CheckoutSession or create a BuyerContactDetails value object?"
> A: "yes add directly to checkout session and we'll have to persist somewhere"

### 10. Asterisk Integration - Two Payloads

**Decision**: Define two interface boundaries - outbound payload and inbound webhook.

#### A. CallExecutionPayload (we send to Asterisk)

```php
class CallExecutionPayload
{
    public string $callRequestId;
    public string $consultantPhone;
    public string $clientPhone;
    public int $durationLimitMinutes;
    public ?DateTimeImmutable $scheduledExecutionTime;
    public string $pricingModel;
    public string $correlationId;
    public array $metadata;  // Includes all session context
}
```

**Key decision from discussion**:
> Q: "What fields does Asterisk need?"
> A: "yes at minimum what's needed, all the session info-- send everything for now in some JSON no point in early filtering"

#### B. CallCompletionEvent (Asterisk sends to us via webhook)

```php
class CallCompletionEvent
{
    public string $callRequestId;
    public string $status;  // 'completed' | 'failed' | 'no_answer'
    public DateTimeImmutable $startedAt;
    public DateTimeImmutable $endedAt;
    public int $durationMinutes;
    public bool $consultantAnswered;
    public bool $clientAnswered;
    public string $disconnectReason;  // 'normal' | 'consultant_hangup' | 'client_hangup' | 'timeout' | 'error'
}
```

**Key decision from discussion**:
> Q: "Is there a callback from Asterisk when call completes?"
> A: "yes assume a callback from Asterisk when call completes"

### 11. Pricing Models - PAYG vs Prepaid

**Decision**: Create enum and track pricing model in CallRequest.

```php
enum PricingModel: string
{
    case PAY_AS_YOU_GO = 'payg';
    case PREPAID_TIME = 'prepaid';
}
```

**Behavior differences**:

**PAYG (Pay As You Go)**:
- Consultant lists service with per-minute rate
- Client pre-authorizes (e.g., $50)
- Actual call duration tracked via Asterisk webhook
- Actual charge = duration * rate (up to pre-auth limit)
- `actualCallDurationMinutes` MUST be populated from webhook

**Prepaid Time**:
- Consultant lists service with fixed time package (e.g., "30 min consultation for $50")
- Client pays fixed amount upfront
- Call disconnects automatically at duration limit
- `prepaidMinutes` field populated
- No variable billing needed

**Key decision from discussion**:
> Q: "Do we need to track which pricing model?"
> A: "we must track. for PAYG yes actual call duration. downstream system will call a webhook with the info"

### 12. Status Lifecycle

**Decision**: Use CallRequestStatus enum with 7 states.

```php
enum CallRequestStatus: string
{
    case PENDING = 'pending';           // Created, not yet sent to Asterisk
    case SCHEDULED = 'scheduled';       // Sent to Asterisk, waiting for execution time
    case IN_PROGRESS = 'in_progress';   // Call is happening now (from Asterisk webhook)
    case COMPLETED = 'completed';       // Call finished successfully
    case SUPERSEDED = 'superseded';     // Replaced by a rescheduled request
    case EXPIRED = 'expired';           // Initiation window passed, never executed
    case FAILED = 'failed';             // Call attempt failed (no answer, error, etc)
}
```

**State transitions**:
- `PENDING` → `SCHEDULED` (when sent to Asterisk)
- `PENDING` → `SUPERSEDED` (when consultant reschedules)
- `SCHEDULED` → `IN_PROGRESS` (Asterisk webhook: call started)
- `SCHEDULED` → `EXPIRED` (initiation window passed)
- `IN_PROGRESS` → `COMPLETED` (Asterisk webhook: call ended successfully)
- `IN_PROGRESS` → `FAILED` (Asterisk webhook: call failed)

## Architecture - Services & Responsibilities

### 1. ConsultantPresenceService

**Responsibilities**:
- Receive presence webhooks from external system
- Update presence repository
- Query current presence for availability checks

**Methods**:
```php
updateFromWebhook(int $consultantId, ConsultantPresence $presence): void
getCurrentPresence(int $consultantId): ?ConsultantPresence
isAvailableForCall(int $consultantId): bool
```

### 2. CallRequestService

**Responsibilities**:
- Create initial CallRequest from CheckoutSession
- Create rescheduled CallRequest (superseding logic)
- Mark requests as expired
- Query requests by various criteria

**Methods**:
```php
createFromCheckoutSession(
    CheckoutSession $session,
    ProductMetadataProvider $productMetadata
): CallRequest

reschedule(
    string $originalRequestId,
    int $delayMinutes,
    ?int $postSessionBufferMinutes = null
): CallRequest

markExpired(string $requestId): void
findPendingRequests(int $consultantId): array
```

### 3. CallExecutionService

**Responsibilities**:
- Build execution payload for Asterisk
- Validate if request can be executed (presence, timing checks)
- Mark request as scheduled

**Methods**:
```php
canExecuteNow(string $requestId): bool
buildExecutionPayload(string $requestId): CallExecutionPayload
markAsScheduled(string $requestId): void
```

### 4. CallCompletionService

**Responsibilities**:
- Handle Asterisk completion webhooks
- Update CallRequest with actual call metrics
- Transition to COMPLETED or FAILED status

**Methods**:
```php
handleCompletion(CallCompletionEvent $event): CallRequest
markInProgress(string $requestId): void
```

### 5. PostPaymentCallRequestWorkflow

**Responsibilities**:
- Concrete implementation of PostPaymentWorkflow interface
- Creates initial CallRequest after successful payment
- Sends to Asterisk for immediate execution if consultant available

**Methods**:
```php
onPaymentConfirmed(CheckoutSession $session, PaymentDetails $payment): void
```

## WooCommerce/Dokan Integration Points

### What Full-Scope Agent Will Implement

1. **Product Configuration UI**
   - Dokan product editor extensions
   - Custom fields: pricing_model, call_duration_minutes, initiation_window_minutes, prepaid_minutes
   - Stored in WooCommerce product metadata

2. **WooCommerceProductMetadataProvider**
   - Implements `ProductMetadataProvider` interface
   - Reads product metadata via `get_post_meta()`

3. **Custom Database Tables**
   - `wp_dlme_call_requests` - stores CallRequest entities
   - `wp_dlme_consultant_presence` - stores latest presence per consultant
   - Migration scripts

4. **Repository Implementations**
   - `WpCallRequestRepository` - uses `$wpdb` for CRUD
   - `WpConsultantPresenceRepository` - uses `$wpdb`
   - `WpCheckoutSessionRepository` - extends existing with buyer contact fields

5. **Webhook Endpoints**
   - `/wp-json/dlme/v1/presence` - receives presence webhooks
   - `/wp-json/dlme/v1/call-completion` - receives Asterisk callbacks
   - Routes to our domain services

6. **Consultant Dashboard UI**
   - Dokan dashboard extension
   - "Call Requests" tab showing pending requests
   - "Take Call Now" / "Capture for Later" buttons
   - Presence status indicator

7. **Payment Hook Integration**
   - Hook into `woocommerce_payment_complete`
   - Instantiate `PostPaymentCallRequestWorkflow`
   - Trigger CallRequest creation

8. **Asterisk Communication**
   - HTTP client to send `CallExecutionPayload`
   - Retry logic, error handling
   - API credentials management

## Testing Strategy

### Unit Tests (TDD Approach)

For each domain class, write tests FIRST:

1. **Value Objects** (TimeRange, ConsultantPresence, etc.)
   - Validation rules
   - Immutability
   - Serialization

2. **Entities** (CallRequest, extended CheckoutSession)
   - Construction
   - State transitions
   - Business rules

3. **Services** (all 5 services)
   - Happy paths
   - Edge cases
   - Error conditions
   - Integration between services

4. **Repository Interfaces**
   - In-memory implementations for tests
   - Test all methods

### Test Coverage Requirements

- All new enums, value objects, entities: 100% coverage
- All new services: 100% coverage of business logic
- All existing tests: Must continue to pass (79 tests currently)
- New tests: Estimate 40-60 additional tests

### Integration Testing

After all unit tests pass:
1. Test complete flow: CheckoutSession → CallRequest → Execution → Completion
2. Test rescheduling flow with superseding
3. Test presence integration with availability
4. Verify logging events at all stages

## Success Criteria

This task is complete when:

- ✅ All domain entities, enums, value objects created
- ✅ All interfaces defined (repositories, ProductMetadataProvider)
- ✅ All services implemented with full business logic
- ✅ All unit tests written and passing (TDD: red → green)
- ✅ All existing tests still passing (no regressions)
- ✅ Logging events added for CallRequest lifecycle
- ✅ In-memory test repositories created
- ✅ HANDOFF.md updated with CallRequest subsystem documentation
- ✅ Code follows PSR-12, PHPStan level 6 compliant
- ✅ Changes committed and pushed to branch

## Out of Scope (For SANDBOX Agent)

These are explicitly NOT implemented in this phase:
- WordPress/WooCommerce integration code
- Database migrations or table creation
- Webhook endpoint implementations
- UI components (consultant dashboard, client forms)
- Actual HTTP calls to Asterisk
- Cron/job scheduling for callback execution
- Email/SMS notification sending

## References

- **Spec**: "Smart Call Request & Callback — V0 Product Spec (Draft)"
- **CLAUDE.md**: Contract for SANDBOX agent responsibilities
- **Existing Features**: Availability system, CTA logic, Checkout sessions
- **Existing Tests**: 79 tests, 286 assertions (all must continue passing)

---

**Implementation Start**: 2025-01-19
**Assigned Agent**: SANDBOX (Pure Domain, Framework-Agnostic)
**Approach**: Test-Driven Development (Red → Green → Refactor)

---

## 2025-01-19 — Code Review Follow-Up & Fixes

### New Discoveries

1. **Plugin not wired into WordPress/WooCommerce hooks**  
   - `dlme-marketplace.php` only autoloads Composer; no `add_action`/`add_filter` calls exist.  
   - None of the core services register with WooCommerce checkout/payment hooks, so integration paths are still theoretical.

2. **Call execution scheduling gaps**  
   - `CallExecutionService::canExecuteNow()` ignored `initiationWindowStart`, `scheduledExecutionTime`, and consultant presence.  
   - Result: rescheduled requests could execute immediately and consultants could receive overlapping calls.  
   - Added coverage in `tests/Core/CallExecutionServiceTest.php`.

3. **Reschedule validation missing**  
   - `CallRequestService::reschedule()` allowed any status (including COMPLETED/EXPIRED) and non-positive delays.  
   - Introduced `DomainException` guards and regression tests in `tests/Core/CallRequestServiceTest.php`.

4. **Buyer contact data never persisted**  
   - `ButtonClickContext` / `CheckoutService` never captured buyer phone/email, leaving execution payloads with blank numbers.  
   - Added optional contact fields to click context, propagated through checkout sessions and into call requests.

5. **Test execution blocked**  
   - `make test` and `composer test` currently fail locally because Composer/PHP binaries are missing both on host and inside Docker image (`composer: not found`).  
   - Documented inability to run automated tests; manual verification pending environment fix.

### Fixes Implemented

- Extended `ButtonClickContext::now()` and `CheckoutService::startFromClick()` to store buyer phone/email so `CallRequestService` passes real numbers to execution payloads.
- Strengthened `CallExecutionService::canExecuteNow()` to enforce scheduled windows and idle presence before allowing execution.
- Added validation to `CallRequestService::reschedule()` to require pending/scheduled status and positive delay, raising `DomainException` otherwise.
- Introduced new Pest suites:
  - `tests/Core/CallExecutionServiceTest.php` for scheduling/presence behavior.
  - Additional cases in `tests/Core/CheckoutServiceTest.php` and `tests/Core/CallRequestServiceTest.php` for contact persistence and reschedule guards.

### Outstanding Work

- Implement real WordPress/Dokan/WooCommerce hooks (bootstrap layer) so services can be integration-tested.
- Replace in-memory repositories with `$wpdb`/WooCommerce backed implementations for persistence.
- Provision PHP + Composer binaries (host or Docker) to restore `make test`/`composer test` capabilities.
