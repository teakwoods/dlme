# DLme Marketplace - Functional Specification

**Version**: 1.0
**Date**: January 19, 2025
**Status**: Active Development
**Project**: WordPress/WooCommerce/Dokan Marketplace for Consultant-Client Connections

---

## Table of Contents

1. [Overview](#1-overview)
2. [System Architecture Principles](#2-system-architecture-principles)
3. [Feature 1: Seller Availability Management](#3-feature-1-seller-availability-management)
4. [Feature 2: Call-to-Action (CTA) Decision Logic](#4-feature-2-call-to-action-cta-decision-logic)
5. [Feature 3: Checkout & Payment Flow](#5-feature-3-checkout--payment-flow)
6. [Feature 4: Call Request & Scheduling](#6-feature-4-call-request--scheduling)
7. [Feature 5: Consultant Presence Tracking](#7-feature-5-consultant-presence-tracking)
8. [Feature 6: Call Execution & Completion](#8-feature-6-call-execution--completion)
9. [Feature 7: Structured Logging](#9-feature-7-structured-logging)
10. [Product Metadata & Configuration](#10-product-metadata--configuration)
11. [Data Models & Persistence](#11-data-models--persistence)
12. [Integration Points](#12-integration-points)
13. [Non-Functional Requirements](#13-non-functional-requirements)

---

## 1. Overview

### 1.1 Purpose

DLme Marketplace is a WordPress-based platform enabling consultants to offer instant or scheduled consultations to clients. The system manages availability, pricing, scheduling, and call coordination to minimize missed connections between consultants and clients.

### 1.2 Core Problem Statement

**Current State**: When a client tries to reach a consultant, if the consultant is:
- Already on a call, or
- Not ready/available at that exact moment

The client often **disappears** (hangs up, gets frustrated, never tries again).

**Desired State**: Create a low-friction system where:
- Clients can easily "get in line" or "be on standby" instead of bouncing
- Consultants can accept or defer calls with a single tap, while capturing that client and reconnecting later
- **Result**: Fewer missed connections, more completed sessions, no extra workload

### 1.3 User Roles

1. **Client/Buyer**: Individual seeking consultation, pays for service
2. **Consultant/Seller**: Professional offering consultation services
3. **System Administrator**: Manages platform configuration
4. **External Call System (Asterisk)**: Handles actual call routing and execution

---

## 2. System Architecture Principles

### 2.1 Framework-Agnostic Core

**REQ-ARCH-001**: Core domain logic MUST be framework-agnostic (pure PHP)
- No WordPress, WooCommerce, or Dokan dependencies in core layer
- All WordPress integration handled by "full-scope agent" integration layer

**REQ-ARCH-002**: All core logic MUST reside in `src/wp-content/plugins/dlme-marketplace/src/Core/`

**REQ-ARCH-003**: Use PSR-4 autoloading with `DLme\` namespace

### 2.2 Testing & Quality

**REQ-ARCH-004**: Test-Driven Development (TDD) approach required
- Write tests FIRST (red)
- Implement minimum code to pass (green)
- Refactor

**REQ-ARCH-005**: All tests MUST be pure PHP with NO WordPress dependencies
- Use in-memory test implementations
- No database, HTTP, or file system dependencies

**REQ-ARCH-006**: Code MUST comply with:
- PSR-12 formatting
- PHPStan level 6 static analysis
- PHP 8.2+ features (native enums, readonly properties)

### 2.3 Design Patterns

**REQ-ARCH-007**: Use Repository Pattern for all persistence
- Define interfaces in Core layer
- Implement in-memory versions for tests
- Full-scope agent implements WordPress-backed versions

**REQ-ARCH-008**: Use Dependency Injection throughout
- Constructor injection for all dependencies
- No service locators or global state

**REQ-ARCH-009**: Immutable value objects with validation
- Use readonly properties
- Validation in constructor
- Type-safe throughout

---

## 3. Feature 1: Seller Availability Management

### 3.1 Manual Availability Flag

**REQ-AVAIL-001**: Each seller MUST have a boolean "Available Now" flag
- Toggleable by seller via dashboard
- Persisted per seller
- Default: `false` (offline)

**REQ-AVAIL-002**: The system MUST provide `isSellerAvailableNow(int $sellerId): bool` method
- Returns `true` ONLY if manual flag is `true`
- Used by CTA decision logic

**REQ-AVAIL-003**: Availability status MUST be represented as enum
- Values: `AVAILABLE_NOW`, `OFFLINE`
- Used for logging and CTA decisions

### 3.2 Weekly Schedules

**REQ-AVAIL-004**: Each seller CAN define a weekly schedule
- Specify available hours per day of week
- Multiple time ranges per day allowed
- Example: Monday 9:00-12:00, 14:00-17:00

**REQ-AVAIL-005**: Time ranges MUST be validated:
- Start hour: 0-23
- End hour: 1-24
- Start < End
- No overlapping ranges within a day
- All 7 days of week present (empty arrays for unavailable days)

**REQ-AVAIL-006**: Schedules MUST be automatically normalized:
- Missing days added with empty arrays
- Ranges sorted by start time
- Duplicates removed

**REQ-AVAIL-007**: System MUST provide `isWithinScheduledHours()` method
- Checks if a given datetime falls within seller's schedule
- Handles timezone conversion
- **NOTE**: In v1, NOT used for availability gating (future feature)

### 3.3 Timezone Support

**REQ-AVAIL-008**: Each seller CAN have a timezone preference
- Stored per seller
- Used for schedule interpretation

**REQ-AVAIL-009**: System MUST default to PST timezone
- When seller has no timezone configured
- When timezone provider unavailable

**REQ-AVAIL-010**: Schedule checking MUST handle timezone conversion
- Convert current time to seller's timezone
- Compare against schedule in seller's local time

### 3.4 Availability v1 Behavior

**REQ-AVAIL-011**: In version 1, availability gating MUST:
- Only check manual "Available Now" flag
- Ignore schedule for gating (schedule stored but not enforced)
- Log availability decisions

**REQ-AVAIL-012**: Future versions MAY:
- Use schedules for automatic availability
- Combine manual flag AND schedule
- Add "busy" state detection

---

## 4. Feature 2: Call-to-Action (CTA) Decision Logic

### 4.1 Context-Aware Button Configuration

**REQ-CTA-001**: System MUST decide which button to show based on:
- Seller availability status
- Page type (seller landing vs product page)
- Product type (instant consultation vs other)

**REQ-CTA-002**: System MUST support two page types:
- `SELLER_LANDING`: Consultant's profile/landing page
- `PRODUCT_PAGE`: Individual product listing page

**REQ-CTA-003**: System MUST support multiple CTA types:
- `INSTANT_CHECKOUT`: "Talk to me now"
- `CONTACT_SELLER`: "Contact me"
- `MESSAGE_SELLER`: "Send message"
- `DISABLED`: "Notify Me" or "Learn More"

### 4.2 Seller Landing Page CTA Rules

**REQ-CTA-004**: On seller landing page:
- If `AVAILABLE_NOW` → Show `INSTANT_CHECKOUT` ("Talk to me now")
- If `OFFLINE` → Show `CONTACT_SELLER` ("Contact me")

**REQ-CTA-005**: Seller landing CTA MUST always be enabled
- No disabled state on landing pages

### 4.3 Product Page CTA Rules

**REQ-CTA-006**: On product page:
- If product is NOT instant consultation → `DISABLED` ("Learn More")
- If product IS instant consultation:
  - AND seller `AVAILABLE_NOW` → `INSTANT_CHECKOUT` ("Talk to me now")
  - AND seller `OFFLINE` → `CONTACT_SELLER` ("Contact me")

**REQ-CTA-007**: Product page instant checkout enabled ONLY when:
- Seller status is `AVAILABLE_NOW`
- Product type is instant consultation
- Configurable via `CtaEnabledPolicy` interface

### 4.4 Button Visual Design

**REQ-CTA-008**: Button styles MUST follow Apple Store aesthetic:
- Clean, minimal design
- Four visual variants: `primary`, `secondary`, `ghost`, `disabled`
- Each CTA type has predefined label and visual config

**REQ-CTA-009**: Button labels MUST be concise and action-oriented:
- "Talk to me now" (instant, available)
- "Buy Now" (e-commerce)
- "Contact me" (offline)
- "Notify Me" (disabled)
- "Learn More" (non-instant products)

**REQ-CTA-010**: Button configuration MUST include:
- Label text
- CSS class key
- Visual variant
- Description (for accessibility)

### 4.5 Configurable Enable/Disable Policy

**REQ-CTA-011**: CTA enable/disable logic MUST be configurable
- Interface: `CtaEnabledPolicy`
- Default implementation: enabled ONLY for `AVAILABLE_NOW` + instant
- Can be overridden with custom business rules

**REQ-CTA-012**: System MUST log all CTA decisions
- Event: `dlme.cta.decision`
- Context: seller_id, page_type, availability_status, cta_type, enabled

---

## 5. Feature 3: Checkout & Payment Flow

### 5.1 Button Click Capture

**REQ-CHECKOUT-001**: System MUST capture all button click context:
- Seller ID
- Product ID and SKU
- Page type (where clicked)
- CTA type (which button)
- Availability status (at click time)
- Buyer ID
- Buyer contact info (phone, email)
- Price quoted
- Referrer URL
- Correlation ID (for tracking)

**REQ-CHECKOUT-002**: Click context MUST be frozen at click time
- Subsequent price/availability changes don't affect this checkout

### 5.2 Checkout Session Creation

**REQ-CHECKOUT-003**: System MUST create CheckoutSession with:
- Unique session ID (prefix: `sess_`)
- Idempotency key (for duplicate prevention)
- All click context
- Workflow context (additional metadata)
- Initial status: `PENDING`

**REQ-CHECKOUT-004**: CheckoutSession creation MUST be idempotent
- Same idempotency key → return existing session
- Never create duplicate sessions

**REQ-CHECKOUT-005**: CheckoutSession MUST include buyer contact:
- Buyer phone number (required for calls)
- Buyer email (optional)

### 5.3 Payment Integration

**REQ-CHECKOUT-006**: System MUST build payment handoff payload containing:
- All checkout session metadata
- Quoted price
- Currency
- Workflow type
- Idempotency key (for webhook matching)

**REQ-CHECKOUT-007**: Payment handoff metadata MUST be embedded in payment provider
- Stripe: in payment_intent.metadata
- Allows webhook to reconstruct session context

**REQ-CHECKOUT-008**: System MUST handle payment success webhook:
- Match by idempotency key
- Validate payment details
- Transition session to `SUCCEEDED`
- Trigger `PostPaymentWorkflow` hook

**REQ-CHECKOUT-009**: System MUST handle payment failure:
- Match by idempotency key
- Store failure reason
- Transition session to `FAILED`
- Log failure event

### 5.4 Payment State Transitions

**REQ-CHECKOUT-010**: CheckoutSession status transitions MUST be:
- `PENDING` → `SUCCEEDED` (on payment success)
- `PENDING` → `FAILED` (on payment failure)
- `PENDING` → `EXPIRED` (if timeout occurs)

**REQ-CHECKOUT-011**: State transitions MUST be idempotent
- Repository prevents duplicate transitions
- Multiple webhook calls with same result → no-op

### 5.5 Post-Payment Workflow Hook

**REQ-CHECKOUT-012**: System MUST provide `PostPaymentWorkflow` interface
- Method: `onPaymentConfirmed(CheckoutSession, PaymentDetails)`
- Called AFTER session marked as succeeded
- Used for call request creation (next feature)

**REQ-CHECKOUT-013**: PostPaymentWorkflow MUST be called on EVERY success callback
- Even for already-succeeded sessions
- Repository ensures actual state changes happen only once

---

## 6. Feature 4: Call Request & Scheduling

### 6.1 Call Request Creation from Checkout

**REQ-CALLREQ-001**: System MUST create CallRequest after successful payment
- Triggered via `PostPaymentWorkflow::onPaymentConfirmed()`
- One CallRequest per CheckoutSession

**REQ-CALLREQ-002**: CallRequest MUST be a frozen contract snapshot:
- Links to CheckoutSession ID
- Seller ID, Buyer ID
- Buyer contact info (phone, email)
- Product ID, SKU
- Pricing model (PAYG or Prepaid)
- Agreed price and currency
- Call duration minutes
- Initiation window (start, end)
- Initial status: `PENDING`

**REQ-CALLREQ-003**: System MUST generate unique request ID
- Format: `req_` + 32-character hex string
- UUIDv4 alternative acceptable

### 6.2 Product Metadata Integration

**REQ-CALLREQ-004**: System MUST query product metadata for:
- `pricing_model`: 'payg' or 'prepaid'
- `call_duration_minutes`: Maximum call length
- `initiation_window_minutes`: How long client has to initiate call
- `prepaid_minutes`: For prepaid products only

**REQ-CALLREQ-005**: Product metadata MUST be abstracted via interface
- Interface: `ProductMetadataProvider`
- Full-scope agent implements WooCommerce integration
- SANDBOX uses in-memory test implementation

**REQ-CALLREQ-006**: Initiation window MUST be calculated:
- Start: Payment confirmation time
- End: Start + initiation_window_minutes
- Example: If window is 15 min, call must start within 15 min of purchase

### 6.3 Pricing Models

**REQ-CALLREQ-007**: System MUST support two pricing models:

**PAYG (Pay-As-You-Go)**:
- Client pre-authorizes amount (e.g., $50)
- Actual call duration tracked
- Actual charge = duration × rate (up to pre-auth limit)
- `actualCallDurationMinutes` MUST be populated from completion webhook

**Prepaid Time**:
- Client pays fixed amount for fixed time (e.g., $50 for 30 min)
- Call disconnects automatically at duration limit
- No variable billing
- `prepaidMinutes` field populated

### 6.4 Call Request Status Lifecycle

**REQ-CALLREQ-008**: CallRequest status MUST support transitions:
- `PENDING`: Created, not yet sent to call system
- `SCHEDULED`: Sent to call system, waiting for execution time
- `IN_PROGRESS`: Call is happening now
- `COMPLETED`: Call finished successfully
- `SUPERSEDED`: Replaced by a rescheduled request
- `EXPIRED`: Initiation window passed, never executed
- `FAILED`: Call attempt failed (no answer, error)

**REQ-CALLREQ-009**: Status transitions MUST be:
- `PENDING` → `SCHEDULED` (when sent to Asterisk)
- `PENDING` → `SUPERSEDED` (when consultant reschedules)
- `SCHEDULED` → `IN_PROGRESS` (call started)
- `SCHEDULED` → `EXPIRED` (initiation window passed)
- `IN_PROGRESS` → `COMPLETED` (call ended successfully)
- `IN_PROGRESS` → `FAILED` (call failed)

### 6.5 Rescheduling ("Capture for Later")

**REQ-CALLREQ-010**: Consultant MUST be able to reschedule pending calls
- Input: Delay in minutes (minimum: 5)
- Input: Optional post-session buffer (1, 2, or 10 minutes)
- Creates NEW CallRequest, marks original as `SUPERSEDED`

**REQ-CALLREQ-011**: Rescheduling MUST create bidirectional links:
- Original: `supersededByRequestId` points to new request
- New: `supersededRequestId` points to original request

**REQ-CALLREQ-012**: Rescheduled request MUST:
- Copy all details from original (seller, buyer, pricing, product)
- Use SAME CheckoutSession ID
- Use SAME agreed price
- Calculate NEW initiation window
- Calculate NEW scheduled execution time
- Have status: `SCHEDULED`

**REQ-CALLREQ-013**: Scheduled execution time calculation:
- **Fixed delay**: now + delay_minutes
- **After current session**: estimated_session_end + buffer_minutes
  - Requires consultant presence with `estimatedSessionEnd`
  - Fallback to fixed delay if session info unavailable

**REQ-CALLREQ-014**: Rescheduling validation MUST reject:
- Delay ≤ 0 minutes (throw `DomainException`)
- Requests in terminal states (`COMPLETED`, `FAILED`, `EXPIRED`)
- Only `PENDING` or `SCHEDULED` requests can be rescheduled

### 6.6 Call Request Expiry

**REQ-CALLREQ-015**: System MUST mark requests as expired when:
- Current time > `initiationWindowEnd`
- Request status is `PENDING` or `SCHEDULED`

**REQ-CALLREQ-016**: Expiry checking SHOULD be automated:
- Cron job or background process
- Queries `findExpired(asOf: DateTimeInterface)` from repository
- Marks each as `EXPIRED`

---

## 7. Feature 5: Consultant Presence Tracking

### 7.1 Real-Time Presence Updates

**REQ-PRESENCE-001**: System MUST receive presence updates from external call system
- Via webhook from Asterisk or call management system
- Updates consultant's current availability state

**REQ-PRESENCE-002**: Presence payload MUST include:
- `consultant_id`: Consultant ID
- `status`: 'idle', 'in_call', or 'offline'
- `timestamp`: When this presence was recorded
- `session_id`: External session ID (optional)
- `estimated_session_end`: When current session expected to end (optional)
- `current_call_request_id`: CallRequest ID being executed (optional)

**REQ-PRESENCE-003**: Presence storage MUST use upsert pattern:
- One record per consultant
- Each webhook REPLACES previous presence
- No history tracking (only current state)

### 7.2 Presence-Based Availability

**REQ-PRESENCE-004**: System MUST provide availability check:
- `isAvailableForCall(consultantId): bool`
- Returns `true` ONLY if status is 'idle'
- Returns `false` for 'in_call', 'offline', or no presence

**REQ-PRESENCE-005**: Presence status meanings:
- **'idle'**: Available and not in call, can take calls now
- **'in_call'**: Currently on active call, cannot take calls
- **'offline'**: Not available, cannot take calls

### 7.3 Integration with Rescheduling

**REQ-PRESENCE-006**: "After current session" rescheduling MUST use:
- `estimated_session_end` from presence
- Add consultant-specified buffer
- Calculate: scheduled_time = estimated_session_end + buffer

**REQ-PRESENCE-007**: If presence lacks `estimated_session_end`:
- Fallback to fixed delay
- Log warning about missing session info

### 7.4 Presence Repository

**REQ-PRESENCE-008**: ConsultantPresenceRepository MUST support:
- `updatePresence(consultantId, presence)`: Upsert latest presence
- `getPresence(consultantId)`: Retrieve current presence
- `clearPresence(consultantId)`: Remove presence (for testing/reset)

---

## 8. Feature 6: Call Execution & Completion

### 8.1 Call Execution Readiness

**REQ-EXEC-001**: System MUST validate execution readiness:
- Request exists and is in `PENDING` or `SCHEDULED` status
- Current time is AFTER `initiationWindowStart`
- Current time is BEFORE `initiationWindowEnd`
- If scheduled, current time is AFTER `scheduledExecutionTime`
- Consultant presence is 'idle' (if presence available)

**REQ-EXEC-002**: Validation MUST prevent execution:
- Before initiation window starts (too early)
- After initiation window ends (expired)
- Before scheduled execution time (premature)
- When consultant is not idle (busy or offline)

### 8.2 Execution Payload Construction

**REQ-EXEC-003**: System MUST build execution payload for Asterisk:
- Call request ID
- Consultant phone number
- Client phone number
- Duration limit (in minutes)
- Pricing model ('payg' or 'prepaid')
- Correlation ID
- Scheduled execution time (if applicable)
- Metadata (all session context)

**REQ-EXEC-004**: Execution payload MUST include comprehensive metadata:
- Checkout session ID
- Seller ID, Buyer ID
- SKU, agreed price, currency
- All context for recovery and tracking

**REQ-EXEC-005**: Payload MUST be serializable to JSON:
- `toArray()` method for JSON encoding
- Sent to Asterisk via HTTP/REST

### 8.3 Automated Execution Scheduling

**REQ-EXEC-006**: System SHOULD provide cron/job mechanism:
- Query: `findReadyForExecution(asOf: DateTimeInterface)`
- Returns all `SCHEDULED` requests where `scheduledExecutionTime ≤ asOf`
- Process each: validate, build payload, send to Asterisk

**REQ-EXEC-007**: Execution sending MUST mark request as sent:
- Transition to `SCHEDULED` status (if from `PENDING`)
- Log execution attempt
- Store scheduled time if not already present

### 8.4 Call Completion Tracking

**REQ-EXEC-008**: System MUST receive completion webhook from Asterisk:
- Call request ID
- Status: 'completed', 'failed', or 'no_answer'
- Started at timestamp
- Ended at timestamp
- Duration in minutes
- Consultant answered (boolean)
- Client answered (boolean)
- Disconnect reason

**REQ-EXEC-009**: Completion handling MUST:
- Find CallRequest by ID
- Validate request exists (log error if not found)
- Determine final status (COMPLETED or FAILED)
- Update request with actual metrics:
  - `actualCallStartTime`
  - `actualCallEndTime`
  - `actualCallDurationMinutes`
  - `callCompletedSuccessfully`
- Save updated request

**REQ-EXEC-010**: For PAYG pricing, actual duration MUST be recorded:
- Used for billing calculation
- Actual charge = duration × rate (capped at pre-auth)
- Full-scope agent triggers invoicing

### 8.5 Call State Transitions

**REQ-EXEC-011**: Call execution MUST support state updates:
- `markInProgress(requestId)`: Transition to `IN_PROGRESS`
- `markAsScheduled(requestId)`: Transition to `SCHEDULED`
- `handleCompletion(event)`: Transition to `COMPLETED` or `FAILED`

---

## 9. Feature 7: Structured Logging

### 9.1 PSR-3 Compliance

**REQ-LOG-001**: All logging MUST use PSR-3 `LoggerInterface`
- Default: `NullLogger` (no-op)
- Injected via constructor dependency injection
- Full-scope agent provides actual logger implementation

**REQ-LOG-002**: System MUST provide test logger:
- `ArrayLogger` for in-memory logging in tests
- Stores all log records in array
- Methods: `log()`, `clear()`, `count()`, `hasRecord()`

### 9.2 Log Events by Feature

**Availability Service**:
- **REQ-LOG-003**: `dlme.availability.flag_changed` (info)
  - When: Manual availability flag changes
  - Context: seller_id, availability_status, available

**CTA Service**:
- **REQ-LOG-004**: `dlme.cta.decision` (info)
  - When: CTA decision made for any page
  - Context: seller_id, page_type, availability_status, cta_type, enabled, product_id

**Checkout Service**:
- **REQ-LOG-005**: `dlme.checkout.session_started` (info)
  - When: New checkout session created
  - Context: session_id, seller_id, buyer_id, sku, quoted_price

- **REQ-LOG-006**: `dlme.checkout.session_reused` (debug)
  - When: Idempotent session returned
  - Context: idempotency_key, session_id, status

- **REQ-LOG-007**: `dlme.payment.callback_received` (info)
  - When: Payment webhook received
  - Context: idempotency_key, provider, external_transaction, amount

- **REQ-LOG-008**: `dlme.payment.session_succeeded` (info)
  - When: Payment successful and session updated
  - Context: session_id, seller_id, buyer_id, external_transaction

- **REQ-LOG-009**: `dlme.payment.session_not_found` (error)
  - When: Payment webhook references unknown session
  - Context: idempotency_key

- **REQ-LOG-010**: `dlme.payment.failed` (info)
  - When: Payment failed
  - Context: session_id, seller_id, buyer_id, reason

**Call Request Service**:
- **REQ-LOG-011**: `dlme.call_request.created` (info)
  - When: CallRequest created from checkout
  - Context: call_request_id, checkout_session_id, seller_id, buyer_id, sku, pricing_model, agreed_price

- **REQ-LOG-012**: `dlme.call_request.rescheduled` (info)
  - When: CallRequest rescheduled
  - Context: original_request_id, new_request_id, delay_minutes, scheduled_execution_time

- **REQ-LOG-013**: `dlme.call_request.expired` (info)
  - When: CallRequest marked as expired
  - Context: call_request_id, seller_id, buyer_id

**Presence Service**:
- **REQ-LOG-014**: `dlme.presence.updated` (info)
  - When: Consultant presence updated from webhook
  - Context: consultant_id, status, timestamp, session_id

**Execution Service**:
- **REQ-LOG-015**: `dlme.call_execution.payload_built` (info)
  - When: Execution payload built for Asterisk
  - Context: call_request_id, pricing_model, duration_limit_minutes

- **REQ-LOG-016**: `dlme.call_execution.scheduled` (info)
  - When: Request marked as scheduled
  - Context: call_request_id

- **REQ-LOG-017**: `dlme.call_execution.in_progress` (info)
  - When: Call marked as in progress
  - Context: call_request_id

**Completion Service**:
- **REQ-LOG-018**: `dlme.call_completion.received` (info)
  - When: Completion webhook received
  - Context: call_request_id, status, duration_minutes, consultant_answered, client_answered

- **REQ-LOG-019**: `dlme.call_completion.request_not_found` (error)
  - When: Completion webhook references unknown request
  - Context: call_request_id

- **REQ-LOG-020**: `dlme.call_completion.processed` (info)
  - When: Completion processed and request updated
  - Context: call_request_id, final_status, actual_duration_minutes, pricing_model

### 9.3 Context Filtering

**REQ-LOG-021**: All log contexts MUST filter null/empty values:
- Remove null values
- Remove empty strings
- Keep 0, false (meaningful values)
- Use `buildContext()` helper in all services

---

## 10. Product Metadata & Configuration

### 10.1 Product Configuration Requirements

**REQ-PRODUCT-001**: Each consultation product MUST define:
- Pricing model: 'payg' or 'prepaid'
- Call duration in minutes
- Initiation window in minutes
- Prepaid minutes (for prepaid products only)

**REQ-PRODUCT-002**: Product metadata MUST be stored in WooCommerce:
- Custom product fields
- Editable via Dokan product editor by consultants
- Persisted in WP post metadata

### 10.2 ProductMetadataProvider Interface

**REQ-PRODUCT-003**: Core layer MUST define interface:
```php
interface ProductMetadataProvider {
    getPricingModel(string $sku): PricingModel;
    getCallDurationMinutes(string $sku): int;
    getInitiationWindowMinutes(string $sku): int;
    getPrepaidMinutes(string $sku): ?int;
}
```

**REQ-PRODUCT-004**: Full-scope agent MUST implement:
- `WooCommerceProductMetadataProvider`
- Reads from WC product metadata
- Caches results for performance

**REQ-PRODUCT-005**: SANDBOX agent uses:
- `InMemoryProductMetadataProvider`
- Configured programmatically in tests
- No WooCommerce dependency

### 10.3 Product Types

**REQ-PRODUCT-006**: System MUST distinguish product types:
- **Instant Consultation**: Immediate call available when consultant online
- **Other Products**: Standard WooCommerce products (not instant)

**REQ-PRODUCT-007**: Product type determination:
- Stored in product metadata or taxonomy
- Used by CTA service for button decisions

---

## 11. Data Models & Persistence

### 11.1 Repository Pattern

**REQ-DATA-001**: All persistence MUST use Repository Pattern:
- Core defines interfaces
- In-memory implementations for tests
- WordPress implementations by full-scope agent

**REQ-DATA-002**: Required repository interfaces:
- `SellerAvailabilityRepository`
- `CheckoutSessionRepository`
- `CallRequestRepository`
- `ConsultantPresenceRepository`

### 11.2 Database Tables (Full-Scope Agent Implementation)

**REQ-DATA-003**: Custom table `wp_dlme_call_requests`:
- Primary key: `id` (VARCHAR, req_xxx)
- Foreign keys: `checkout_session_id`, `seller_id`, `buyer_id`
- Indexes: `status`, `scheduled_execution_time`, `checkout_session_id`
- All CallRequest fields stored

**REQ-DATA-004**: Custom table `wp_dlme_consultant_presence`:
- Primary key: `consultant_id`
- Upsert pattern (one row per consultant)
- Stores: status, timestamp, session info
- Index: `consultant_id`

**REQ-DATA-005**: CheckoutSession storage:
- Options:
  - WooCommerce orders with custom meta
  - Custom table
  - Session storage
- Must persist all CheckoutSession fields

**REQ-DATA-006**: Seller availability storage:
- User meta: `dlme_available_now` (boolean)
- User meta: `dlme_schedule` (JSON)
- User meta: `dlme_timezone` (string)

### 11.3 Value Objects & Immutability

**REQ-DATA-007**: All value objects MUST be immutable:
- Use `readonly` properties
- All fields set in constructor
- No setter methods

**REQ-DATA-008**: Required value objects:
- `TimeRange`: Hour range with validation
- `SellerSchedule`: Weekly schedule
- `ButtonClickContext`: Click metadata
- `CheckoutSession`: Checkout state
- `PaymentDetails`: Payment info
- `PaymentHandoffPayload`: Stripe payload
- `WorkflowContext`: Workflow metadata
- `ConsultantPresence`: Presence snapshot
- `CallRequest`: Call contract
- `CallExecutionPayload`: Asterisk payload
- `CallCompletionEvent`: Completion webhook data

### 11.4 Enums

**REQ-DATA-009**: All enums MUST use PHP 8.2 native enums:
- Backed by string values for persistence
- No parent inheritance issues

**REQ-DATA-010**: Required enums:
- `DayOfWeek`: mon, tue, wed, thu, fri, sat, sun
- `AvailabilityStatus`: available_now, offline
- `CtaType`: instant_checkout, contact_seller, message_seller, disabled
- `PageType`: seller_landing, product_page
- `ButtonVisualVariant`: primary, secondary, ghost, disabled
- `CheckoutSessionStatus`: pending, succeeded, failed, expired
- `CallRequestStatus`: pending, scheduled, in_progress, completed, superseded, expired, failed
- `PricingModel`: payg, prepaid

---

## 12. Integration Points

### 12.1 WordPress/WooCommerce Integration

**REQ-INT-001**: Full-scope agent MUST create:
- REST API endpoints for all operations
- Admin UI for consultant dashboard
- WooCommerce product extensions
- Dokan integration for consultant product management

**REQ-INT-002**: Required REST endpoints:
- `POST /dlme/v1/checkout/start`: Create checkout session
- `POST /dlme/v1/webhooks/stripe`: Stripe webhook handler
- `POST /dlme/v1/presence`: Presence webhook receiver
- `POST /dlme/v1/call-completion`: Completion webhook receiver
- `POST /dlme/v1/call-requests/reschedule`: Reschedule call

### 12.2 Stripe Integration

**REQ-INT-003**: Payment provider integration MUST:
- Create Stripe Checkout Sessions
- Embed all metadata in payment_intent
- Handle webhooks: `payment_intent.succeeded`, `payment_intent.payment_failed`
- Verify webhook signatures

**REQ-INT-004**: Idempotency key MUST be:
- Embedded in Stripe metadata
- Used to match webhooks to CheckoutSessions
- Unique per checkout attempt

### 12.3 Asterisk/FastAGI Integration

**REQ-INT-005**: Call system integration MUST:
- Receive execution payloads via REST API
- Initiate calls to both consultant and client
- Bridge calls when both answer
- Enforce duration limits
- Send completion webhooks to WordPress

**REQ-INT-006**: Asterisk webhook MUST include:
- Call request ID (for matching)
- Call outcome (completed/failed/no_answer)
- Actual duration (for PAYG billing)
- Participant answer status
- Disconnect reason

### 12.4 External Call System Contract

**REQ-INT-007**: External call system MUST support:
- REST API for call initiation
- Webhook callbacks for call events:
  - Call started
  - Call completed
  - Call failed
- Duration tracking and reporting
- Automatic disconnect at duration limit

---

## 13. Non-Functional Requirements

### 13.1 Performance

**REQ-NFR-001**: All database queries MUST use indexes:
- CallRequest queries by status
- CallRequest queries by scheduled_execution_time
- Presence queries by consultant_id

**REQ-NFR-002**: Presence updates MUST be fast:
- Upsert operation (not insert + update)
- Single table lookup
- No complex joins

**REQ-NFR-003**: Repository queries MUST be optimized:
- Limit result sets (e.g., max 100 ready requests per cron run)
- Use appropriate indexes
- Avoid N+1 query problems

### 13.2 Scalability

**REQ-NFR-004**: System MUST handle:
- Multiple concurrent checkouts
- High volume of presence updates
- Multiple scheduled callbacks executing simultaneously

**REQ-NFR-005**: Cron jobs MUST be rate-limited:
- Process max N requests per execution
- Avoid overwhelming Asterisk
- Implement backoff on failures

### 13.3 Reliability

**REQ-NFR-006**: All state transitions MUST be idempotent:
- Repeated webhook calls → same result
- No duplicate CallRequests
- No duplicate charges

**REQ-NFR-007**: System MUST handle failures gracefully:
- Asterisk unavailable → log error, retry later
- Stripe webhook failure → return error, Stripe retries
- Database errors → rollback, log, alert

**REQ-NFR-008**: Logging MUST be comprehensive:
- All state changes logged
- All external API calls logged
- All errors logged with context

### 13.4 Security

**REQ-NFR-009**: Webhook endpoints MUST verify signatures:
- Stripe: Verify webhook signature
- Asterisk: Verify API token or signature
- Reject unsigned/invalid webhooks

**REQ-NFR-010**: Sensitive data MUST be protected:
- Phone numbers stored securely
- Payment details (card info) NOT stored locally
- External transaction IDs only

**REQ-NFR-011**: API endpoints MUST enforce authentication:
- REST endpoints require WordPress authentication
- Consultant actions require consultant role
- Admin actions require admin role

### 13.5 Maintainability

**REQ-NFR-012**: Code MUST be well-documented:
- PHPDoc for all public methods
- README for integration guide
- HANDOFF.md for full-scope agent
- DEVELOPER_FLOW_GUIDE.md for onboarding

**REQ-NFR-013**: Tests MUST be maintained:
- All new features require tests
- All bug fixes require regression tests
- Test coverage >80% for core logic

**REQ-NFR-014**: Configuration MUST be externalized:
- Product metadata in WooCommerce
- System settings in WordPress options
- No hardcoded business rules in code

### 13.6 Observability

**REQ-NFR-015**: All critical paths MUST be logged:
- Checkout flow: session creation → payment → call request
- Call flow: scheduling → execution → completion
- Errors: all exceptions logged with stack trace

**REQ-NFR-016**: Metrics SHOULD be tracked:
- Number of checkouts per day
- Number of calls completed
- Average call duration (PAYG)
- Reschedule rate
- Expiry rate

---

## Appendices

### A. Glossary

- **Consultant/Seller**: Professional offering consultation services
- **Client/Buyer**: Individual purchasing consultation
- **Call Request**: Domain entity representing consultation contract
- **Checkout Session**: Domain entity representing payment flow
- **CTA**: Call-to-Action button
- **PAYG**: Pay-As-You-Go pricing model
- **Prepaid**: Fixed-price, fixed-duration pricing model
- **Presence**: Real-time consultant availability status
- **Idempotency**: Property ensuring repeated operations produce same result
- **Supersede**: Replace with new version (for rescheduling)

### B. Design Decisions

See `agent_journal.md` for complete design decision rationale including:
- Why CallRequest is separate from CheckoutSession
- Why custom tables vs WooCommerce orders
- Why presence is webhook-driven vs polling
- Why rescheduling creates new entity vs updating
- All Q&A from implementation phase

### C. Integration Guide

See `HANDOFF.md` for full-scope agent integration instructions including:
- WordPress/WooCommerce integration steps
- Database schema details
- REST endpoint specifications
- UI component requirements
- Webhook handler implementations

### D. Developer Onboarding

See `DEVELOPER_FLOW_GUIDE.md` for end-to-end code tracing including:
- Complete flow from landing page to call completion
- File/class references for each step
- State transition diagrams
- Common debug scenarios

---

**END OF FUNCTIONAL SPECIFICATION**
