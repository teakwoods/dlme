# Developer Flow Guide: End-to-End Call Request Journey

**Purpose**: Trace the complete code path from a client viewing a consultant's landing page through successful call completion.

**Audience**: New developers onboarding to the DLme marketplace codebase.

---

## Table of Contents

1. [Overview: The Big Picture](#overview-the-big-picture)
2. [Phase 1: Consultant Landing Page](#phase-1-consultant-landing-page)
3. [Phase 2: Client Clicks "Call Now"](#phase-2-client-clicks-call-now)
4. [Phase 3: Payment Processing](#phase-3-payment-processing)
5. [Phase 4: Call Request Creation](#phase-4-call-request-creation)
6. [Phase 5: Consultant Captures for Later](#phase-5-consultant-captures-for-later)
7. [Phase 6: Scheduled Callback Execution](#phase-6-scheduled-callback-execution)
8. [Phase 7: Call Completion](#phase-7-call-completion)
9. [Data Flow Summary](#data-flow-summary)

---

## Overview: The Big Picture

### The Journey

```
Client visits consultant page
    ↓
Sees "Talk to me now" button (CTA)
    ↓
Clicks button → Checkout flow
    ↓
Pays via Stripe/payment provider
    ↓
Payment confirmed → CallRequest created
    ↓
[Branch A: Consultant available]
    → Call starts immediately

[Branch B: Consultant busy]
    → Client waits
    → Consultant taps "Capture for Later"
    → Chooses delay (10 min)
    → New CallRequest scheduled
    ↓
At scheduled time → Asterisk calls both parties
    ↓
Call completes → Duration tracked for billing
```

### The Code Layers

**This guide focuses on the CORE domain layer** (framework-agnostic PHP):
- `src/wp-content/plugins/dlme-marketplace/src/Core/` - All domain logic

**Full-scope agent handles** (not covered in detail here):
- WordPress/WooCommerce integration
- REST API endpoints
- Database persistence
- UI rendering
- Webhook receivers

---

## Phase 1: Consultant Landing Page

### User Experience
Client navigates to `https://site.com/consultant/jane-smith/` and sees:
- Consultant name, photo, description
- **"Talk to me now"** button (if available)
- Or **"Contact me"** button (if offline)

### Code Flow

#### Step 1.1: Determine Consultant Availability

**File**: `src/Core/AvailabilityService.php`

**Full-scope agent calls**:
```php
$availabilityService = new AvailabilityService($repository, $timezoneProvider, $logger);

// Check if consultant is available right now
$isAvailable = $availabilityService->isSellerAvailableNow($consultantId);

// Or get the enum status
$status = $availabilityService->getAvailabilityStatus($consultantId);
// Returns: AvailabilityStatus::AVAILABLE_NOW or AvailabilityStatus::OFFLINE
```

**What happens inside**:
1. Service queries `SellerAvailabilityRepository` for manual "available now" flag
2. In v1: Only checks manual flag (schedules are stored but not used for gating)
3. Returns boolean or enum status

**Key Classes**:
- `AvailabilityService` - Main service
- `SellerAvailabilityRepository` - Interface for persistence (full-scope implements)
- `AvailabilityStatus` - Enum: `AVAILABLE_NOW` | `OFFLINE`

#### Step 1.2: Decide Which Button to Show

**File**: `src/Core/CallToActionService.php`

**Full-scope agent calls**:
```php
$ctaService = new CallToActionService(
    $availabilityService,
    $styleConfig,
    $enabledPolicy,
    $logger
);

// Decide CTA for seller landing page
$decision = $ctaService->decideForSellerLanding($consultantId);
```

**What happens inside**:
1. Gets availability status from `AvailabilityService`
2. Maps status to CTA type:
   - `AVAILABLE_NOW` → `CtaType::INSTANT_CHECKOUT` ("Talk to me now")
   - `OFFLINE` → `CtaType::CONTACT_SELLER` ("Contact me")
3. Gets visual config from `CtaStyleConfig`:
   - Label text
   - CSS class
   - Visual variant
4. Checks `CtaEnabledPolicy` to determine if button is enabled
5. Logs decision via PSR-3 logger
6. Returns `CtaDecision` object

**Key Classes**:
- `CallToActionService` - Main CTA logic
- `CtaDecision` - Value object: `{ type, config, enabled }`
- `CtaType` - Enum: `INSTANT_CHECKOUT` | `CONTACT_SELLER` | etc.
- `CtaStyleConfig` - Button visual configuration
- `CtaEnabledPolicy` - Interface for enable/disable rules
- `DefaultCtaEnabledPolicy` - Default: only enabled for AVAILABLE_NOW + instant

**Full-scope agent then**:
```php
// Render button in template
echo '<button
    class="' . esc_attr($decision->config->cssKey) . '"
    data-seller-id="' . esc_attr($consultantId) . '"
    ' . ($decision->enabled ? '' : 'disabled') . '>
    ' . esc_html($decision->config->label) . '
</button>';
```

**Logged Event**: `dlme.cta.decision` with context: seller_id, page_type, availability_status, cta_type, enabled

---

## Phase 2: Client Clicks "Call Now"

### User Experience
Client clicks the **"Talk to me now"** button. JavaScript captures the click and initiates checkout flow.

### Code Flow

#### Step 2.1: Capture Button Click Context

**File**: `src/Core/ButtonClickContext.php`

**Full-scope agent (JavaScript → PHP)**:
```javascript
// JavaScript on page
button.addEventListener('click', async (e) => {
    const clickData = {
        sellerId: button.dataset.sellerId,
        productId: button.dataset.productId,
        sku: button.dataset.sku,
        price: button.dataset.price,
        pageType: 'seller_landing',
        ctaType: 'instant_checkout',
        availabilityStatus: 'available_now',
        referrerUrl: window.location.href,
        buyerId: currentUser.id,
        buyerPhone: currentUser.phone,
        buyerEmail: currentUser.email
    };

    // POST to WordPress REST API
    const response = await fetch('/wp-json/dlme/v1/checkout/start', {
        method: 'POST',
        body: JSON.stringify(clickData)
    });
});
```

**WordPress REST endpoint handler**:
```php
// Full-scope agent creates this endpoint
add_action('rest_api_init', function() {
    register_rest_route('dlme/v1', '/checkout/start', [
        'methods' => 'POST',
        'callback' => 'dlme_handle_checkout_start',
    ]);
});

function dlme_handle_checkout_start(WP_REST_Request $request) {
    $data = $request->get_json_params();

    // Build ButtonClickContext
    $click = ButtonClickContext::now(
        sellerId: (int) $data['sellerId'],
        productId: (int) $data['productId'],
        pageType: PageType::from($data['pageType']),
        availabilityStatus: AvailabilityStatus::from($data['availabilityStatus']),
        ctaType: CtaType::from($data['ctaType']),
        sku: $data['sku'],
        price: (float) $data['price'],
        referrerUrl: $data['referrerUrl'],
        buyerId: (int) $data['buyerId'],
        correlationId: wp_generate_uuid4()
    );

    // Generate idempotency key
    $idempotencyKey = 'idem_' . bin2hex(random_bytes(16));

    // Start checkout session
    $checkoutService = get_checkout_service(); // DI container

    $workflow = new WorkflowContext('instant_call', [
        'priority' => 'high',
        'buyer_phone' => $data['buyerPhone'],
        'buyer_email' => $data['buyerEmail']
    ]);

    $session = $checkoutService->startFromClick($click, $idempotencyKey, $workflow);

    // Return session info to client
    return new WP_REST_Response([
        'session_id' => $session->id,
        'idempotency_key' => $idempotencyKey
    ]);
}
```

#### Step 2.2: Create Checkout Session

**File**: `src/Core/CheckoutService.php`

**Method**: `startFromClick()`

**What happens inside**:
```php
public function startFromClick(
    ButtonClickContext $click,
    string $idempotencyKey,
    WorkflowContext $workflowContext
): CheckoutSession {
    // 1. Check for existing session with this idempotency key
    $existing = $this->sessionRepository->findByIdempotencyKey($idempotencyKey);

    if ($existing !== null) {
        // Idempotency: return existing session
        $this->logger->debug('dlme.checkout.session_reused', [...]);
        return $existing;
    }

    // 2. Generate new session ID
    $sessionId = 'sess_' . bin2hex(random_bytes(16));

    // 3. Extract buyer contact from workflow context or click
    $buyerPhone = $workflowContext->metadata['buyer_phone'] ?? null;
    $buyerEmail = $workflowContext->metadata['buyer_email'] ?? null;

    // 4. Create new session
    $session = new CheckoutSession(
        id: $sessionId,
        idempotencyKey: $idempotencyKey,
        sellerId: $click->sellerId,
        buyerId: $click->buyerId,
        productId: $click->productId,
        sku: $click->sku,
        quotedPrice: $click->price,
        pageType: $click->pageType,
        ctaType: $click->ctaType,
        availabilityStatus: $click->availabilityStatus,
        referrerUrl: $click->referrerUrl,
        correlationId: $click->correlationId,
        workflowContext: $workflowContext,
        createdAt: new DateTimeImmutable(),
        status: CheckoutSessionStatus::PENDING,
        buyerPhone: $buyerPhone,  // NEW: Added in CallRequest phase
        buyerEmail: $buyerEmail   // NEW: Added in CallRequest phase
    );

    // 5. Save to repository
    $this->sessionRepository->save($session);

    // 6. Log creation
    $this->logger->info('dlme.checkout.session_started', [...]);

    return $session;
}
```

**Key Classes**:
- `CheckoutService` - Main checkout orchestration
- `CheckoutSession` - Value object representing checkout state
- `CheckoutSessionStatus` - Enum: `PENDING` | `SUCCEEDED` | `FAILED` | `EXPIRED`
- `CheckoutSessionRepository` - Interface for persistence
- `ButtonClickContext` - Captures all click metadata
- `WorkflowContext` - Captures workflow-specific metadata

**Logged Event**: `dlme.checkout.session_started` with full context

---

## Phase 3: Payment Processing

### User Experience
Client is redirected to Stripe Checkout (or other payment provider) to complete payment.

### Code Flow

#### Step 3.1: Build Payment Handoff Payload

**File**: `src/Core/CheckoutService.php`

**Method**: `buildPaymentHandoffPayload()`

**Full-scope agent calls**:
```php
// Before redirecting to Stripe
$payload = $checkoutService->buildPaymentHandoffPayload($session);

// Create Stripe Checkout Session
$stripeSession = \Stripe\Checkout\Session::create([
    'line_items' => [[
        'price_data' => [
            'currency' => 'usd',
            'product_data' => [
                'name' => 'Instant Consultation',
            ],
            'unit_amount' => $session->quotedPrice * 100, // Stripe uses cents
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => site_url('/checkout/success?session_id={CHECKOUT_SESSION_ID}'),
    'cancel_url' => site_url('/checkout/cancel'),
    'metadata' => $payload->metadata, // Pass ALL context to Stripe
]);

// Redirect client to Stripe
wp_redirect($stripeSession->url);
exit;
```

**What `buildPaymentHandoffPayload()` does**:
```php
public function buildPaymentHandoffPayload(CheckoutSession $session): PaymentHandoffPayload
{
    return new PaymentHandoffPayload(
        checkoutSessionId: $session->id,
        idempotencyKey: $session->idempotencyKey,
        sellerId: $session->sellerId,
        buyerId: $session->buyerId,
        sku: $session->sku,
        quotedPrice: $session->quotedPrice,
        currency: 'USD',
        workflowType: $session->workflowContext->workflowType,
        metadata: [
            'dlme_session_id' => $session->id,
            'dlme_idempotency_key' => $session->idempotencyKey,
            'dlme_seller_id' => $session->sellerId,
            'dlme_buyer_id' => $session->buyerId,
            'dlme_correlation_id' => $session->correlationId,
            // ... all context for recovery
        ]
    );
}
```

**Key Classes**:
- `PaymentHandoffPayload` - Value object with all payment context
- All metadata is embedded so webhook can reconstruct state

**Logged Event**: `dlme.payment.handoff_built`

#### Step 3.2: Stripe Webhook Receives Payment Confirmation

**Full-scope agent webhook handler**:
```php
// WordPress webhook endpoint
add_action('rest_api_init', function() {
    register_rest_route('dlme/v1', '/webhooks/stripe', [
        'methods' => 'POST',
        'callback' => 'dlme_handle_stripe_webhook',
        'permission_callback' => '__return_true', // Stripe signature verification
    ]);
});

function dlme_handle_stripe_webhook(WP_REST_Request $request) {
    // 1. Verify Stripe signature
    $payload = $request->get_body();
    $sig = $request->get_header('stripe-signature');
    $event = \Stripe\Webhook::constructEvent($payload, $sig, STRIPE_WEBHOOK_SECRET);

    // 2. Handle payment_intent.succeeded
    if ($event->type === 'payment_intent.succeeded') {
        $paymentIntent = $event->data->object;
        $metadata = $paymentIntent->metadata;

        // 3. Extract idempotency key
        $idempotencyKey = $metadata['dlme_idempotency_key'];

        // 4. Build PaymentDetails
        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: $paymentIntent->id,
            paymentInstrumentRef: $paymentIntent->payment_method,
            currency: strtoupper($paymentIntent->currency),
            amount: $paymentIntent->amount / 100, // Convert cents to dollars
            paidAt: new DateTimeImmutable('@' . $paymentIntent->created)
        );

        // 5. Mark payment successful in CheckoutService
        $checkoutService = get_checkout_service();
        $session = $checkoutService->markPaymentSuccessful($idempotencyKey, $payment);

        if ($session === null) {
            error_log("Payment successful but session not found: $idempotencyKey");
            return new WP_REST_Response(['error' => 'session_not_found'], 400);
        }

        return new WP_REST_Response(['status' => 'success']);
    }

    return new WP_REST_Response(['status' => 'ignored']);
}
```

#### Step 3.3: Mark Payment Successful

**File**: `src/Core/CheckoutService.php`

**Method**: `markPaymentSuccessful()`

**What happens inside**:
```php
public function markPaymentSuccessful(
    string $idempotencyKey,
    PaymentDetails $payment
): ?CheckoutSession {
    // 1. Log payment callback received
    $this->logger->info('dlme.payment.callback_received', [
        'idempotency_key' => $idempotencyKey,
        'provider' => $payment->provider,
        'external_transaction' => $payment->externalTransactionId,
        'amount' => $payment->amount,
        'currency' => $payment->currency,
    ]);

    // 2. Update session to SUCCEEDED in repository
    $session = $this->sessionRepository->markSucceededWithPayment($idempotencyKey, $payment);

    if ($session === null) {
        // Session not found
        $this->logger->error('dlme.payment.session_not_found', [
            'idempotency_key' => $idempotencyKey,
        ]);
        return null;
    }

    // 3. Verify status transitioned correctly
    if ($session->status !== CheckoutSessionStatus::SUCCEEDED) {
        $this->logger->warning('dlme.payment.unexpected_status_after_success', [
            'idempotency_key' => $idempotencyKey,
            'session_id' => $session->id,
            'status' => $session->status->value,
        ]);
        return $session;
    }

    // 4. Log success
    $this->logger->info('dlme.payment.session_succeeded', [...]);

    // 5. CRITICAL: Call PostPaymentWorkflow hook
    if ($this->postPaymentWorkflow !== null) {
        $this->postPaymentWorkflow->onPaymentConfirmed($session, $payment);
    }

    return $session;
}
```

**Key Point**: The `postPaymentWorkflow` hook is where **CallRequest creation happens** (next phase).

**Key Classes**:
- `PaymentDetails` - Value object with payment info
- `PostPaymentWorkflow` - Interface for post-payment actions
- Repository handles idempotent state transitions

**Logged Events**:
- `dlme.payment.callback_received` (info)
- `dlme.payment.session_succeeded` (info)
- `dlme.payment.session_not_found` (error - if not found)

---

## Phase 4: Call Request Creation

### User Experience
Payment succeeds. Behind the scenes, the system creates a CallRequest entity that will be used to coordinate the actual call.

### Code Flow

#### Step 4.1: PostPaymentWorkflow Hook Fires

**File**: `src/Core/PostPaymentWorkflow.php` (interface)

**Full-scope agent implements**:
```php
class CallRequestWorkflow implements PostPaymentWorkflow
{
    public function __construct(
        private readonly CallRequestService $callRequestService,
        private readonly ProductMetadataProvider $productMetadata,
        private readonly CallExecutionService $callExecutionService,
        private readonly LoggerInterface $logger
    ) {}

    public function onPaymentConfirmed(
        CheckoutSession $session,
        PaymentDetails $payment
    ): void {
        // 1. Create CallRequest from successful checkout
        $callRequest = $this->callRequestService->createFromCheckoutSession($session);

        // 2. Attempt immediate execution if consultant available
        if ($this->callExecutionService->canExecuteNow($callRequest->id)) {
            // Build payload for Asterisk
            $payload = $this->callExecutionService->buildExecutionPayload($callRequest->id);

            // Send to Asterisk via HTTP
            $this->sendToAsterisk($payload);

            // Mark as scheduled
            $this->callExecutionService->markAsScheduled($callRequest->id);
        }

        // If consultant is busy, CallRequest stays in PENDING status
        // Consultant will see it in their dashboard to "capture for later"
    }

    private function sendToAsterisk(CallExecutionPayload $payload): void {
        // HTTP call to Asterisk FastAGI endpoint
        wp_remote_post('https://asterisk.example.com/api/initiate-call', [
            'body' => json_encode($payload->toArray()),
            'headers' => ['Content-Type' => 'application/json'],
        ]);
    }
}

// Register in DI container
add_action('plugins_loaded', function() {
    $workflow = new CallRequestWorkflow(
        get_call_request_service(),
        get_product_metadata_provider(),
        get_call_execution_service(),
        get_logger()
    );

    // Inject into CheckoutService
    $checkoutService = new CheckoutService(
        get_checkout_session_repository(),
        $workflow, // PostPaymentWorkflow implementation
        get_logger()
    );
});
```

#### Step 4.2: Create CallRequest from CheckoutSession

**File**: `src/Core/CallRequestService.php`

**Method**: `createFromCheckoutSession()`

**What happens inside**:
```php
public function createFromCheckoutSession(CheckoutSession $session): CallRequest
{
    $sku = $session->sku ?? '';
    $now = new DateTimeImmutable();

    // 1. Get product metadata (pricing, timing config)
    $pricingModel = $this->productMetadata->getPricingModel($sku);
    $callDurationMinutes = $this->productMetadata->getCallDurationMinutes($sku);
    $initiationWindowMinutes = $this->productMetadata->getInitiationWindowMinutes($sku);
    $prepaidMinutes = $this->productMetadata->getPrepaidMinutes($sku);

    // 2. Calculate initiation window
    $initiationWindowStart = $now;
    $initiationWindowEnd = $now->modify("+{$initiationWindowMinutes} minutes");

    // 3. Generate unique request ID
    $requestId = 'req_' . bin2hex(random_bytes(16));

    // 4. Create CallRequest entity (frozen contract snapshot)
    $request = new CallRequest(
        id: $requestId,
        checkoutSessionId: $session->id,
        sellerId: $session->sellerId,
        buyerId: $session->buyerId ?? 0,
        buyerPhone: $session->buyerPhone ?? '',  // From checkout
        buyerEmail: $session->buyerEmail,         // From checkout
        productId: $session->productId ?? 0,
        sku: $sku,
        pricingModel: $pricingModel,              // From product metadata
        agreedPrice: number_format($session->quotedPrice ?? 0.0, 2, '.', ''),
        currency: 'USD',
        prepaidMinutes: $prepaidMinutes,          // For prepaid products
        createdAt: $now,
        initiationWindowStart: $initiationWindowStart,
        initiationWindowEnd: $initiationWindowEnd,  // Must start by this time
        callDurationMinutes: $callDurationMinutes,  // Max call length
        scheduledExecutionTime: null,             // For rescheduled calls
        status: CallRequestStatus::PENDING,       // Initial status
        actualCallStartTime: null,                // Filled by completion webhook
        actualCallEndTime: null,
        actualCallDurationMinutes: null,          // For PAYG billing
        callCompletedSuccessfully: false,
        correlationId: $session->correlationId ?? '',
        referrerUrl: $session->referrerUrl,
        supersededByRequestId: null,              // For rescheduling
        supersededRequestId: null
    );

    // 5. Save to repository
    $this->repository->save($request);

    // 6. Log creation
    $this->logger->info('dlme.call_request.created', [
        'call_request_id' => $request->id,
        'checkout_session_id' => $request->checkoutSessionId,
        'seller_id' => $request->sellerId,
        'buyer_id' => $request->buyerId,
        'sku' => $request->sku,
        'pricing_model' => $request->pricingModel->value,
        'agreed_price' => $request->agreedPrice,
        'call_duration_minutes' => $request->callDurationMinutes,
        'initiation_window_end' => $request->initiationWindowEnd->format('c'),
    ]);

    return $request;
}
```

**Key Classes**:
- `CallRequest` - Main entity (frozen contract snapshot)
- `CallRequestStatus` - Enum: `PENDING` | `SCHEDULED` | `IN_PROGRESS` | `COMPLETED` | etc.
- `PricingModel` - Enum: `PAY_AS_YOU_GO` | `PREPAID_TIME`
- `ProductMetadataProvider` - Interface to get product config
- `CallRequestRepository` - Interface for persistence

**Logged Event**: `dlme.call_request.created` with full context

#### Step 4.3: Check if Consultant is Available for Immediate Call

**File**: `src/Core/CallExecutionService.php`

**Method**: `canExecuteNow()`

**What happens inside**:
```php
public function canExecuteNow(string $requestId): bool
{
    // 1. Find the request
    $request = $this->repository->findById($requestId);

    if ($request === null) {
        return false;
    }

    // 2. Check status (must be PENDING or SCHEDULED)
    if ($request->status !== CallRequestStatus::PENDING
        && $request->status !== CallRequestStatus::SCHEDULED) {
        return false;
    }

    // 3. Check initiation window hasn't expired
    $now = new DateTimeImmutable();
    if ($now > $request->initiationWindowEnd) {
        return false; // Too late
    }

    // 4. Optionally check consultant presence
    // (Could query ConsultantPresenceService here)

    return true;
}
```

**If available** → Proceeds to build execution payload
**If busy** → CallRequest stays PENDING, consultant sees it in dashboard

**Key Classes**:
- `CallExecutionService` - Validates execution readiness
- `ConsultantPresenceService` - Could be used to check real-time availability

---

## Phase 5: Consultant Captures for Later

### User Experience
Consultant is on another call. Their dashboard shows: "New Call Request from John" with buttons:
- **[Take Call Now]** (disabled - already in call)
- **[Capture for Later]**

Consultant taps **"Capture for Later"**, sees:
- "When should we call back?"
- **[5 min] [10 min] [20 min] [After current session]**

Consultant chooses **"10 min"**.

### Code Flow

#### Step 5.1: Dashboard Shows Pending Requests

**Full-scope agent (dashboard page)**:
```php
// Query pending requests for consultant
$callRequestRepository = get_call_request_repository();
$pendingRequests = $callRequestRepository->findPendingByConsultant($currentConsultantId);

foreach ($pendingRequests as $request) {
    ?>
    <div class="call-request-card">
        <p>New Call Request from <?php echo esc_html($request->buyerPhone); ?></p>
        <p>Product: <?php echo esc_html($request->sku); ?></p>
        <p>Requested: <?php echo $request->createdAt->format('g:i A'); ?></p>

        <button
            class="take-now-btn"
            data-request-id="<?php echo esc_attr($request->id); ?>"
            <?php if ($consultantIsInCall) echo 'disabled'; ?>>
            Take Call Now
        </button>

        <button
            class="capture-later-btn"
            data-request-id="<?php echo esc_attr($request->id); ?>">
            Capture for Later
        </button>
    </div>
    <?php
}
```

#### Step 5.2: Consultant Chooses Delay

**JavaScript on dashboard**:
```javascript
document.querySelector('.capture-later-btn').addEventListener('click', async (e) => {
    const requestId = e.target.dataset.requestId;

    // Show delay picker modal
    const delay = await showDelayPickerModal(); // Returns: 5, 10, 20, or 'after_session'

    // POST to reschedule endpoint
    const response = await fetch('/wp-json/dlme/v1/call-requests/reschedule', {
        method: 'POST',
        body: JSON.stringify({
            request_id: requestId,
            delay_minutes: delay === 'after_session' ? null : delay,
            post_session_buffer: delay === 'after_session' ? 5 : null
        })
    });

    if (response.ok) {
        showNotification('Call rescheduled for ' + delay + ' minutes from now');
    }
});
```

#### Step 5.3: REST API Handles Reschedule

**Full-scope agent endpoint**:
```php
add_action('rest_api_init', function() {
    register_rest_route('dlme/v1', '/call-requests/reschedule', [
        'methods' => 'POST',
        'callback' => 'dlme_handle_call_reschedule',
        'permission_callback' => 'dlme_user_is_consultant',
    ]);
});

function dlme_handle_call_reschedule(WP_REST_Request $request) {
    $data = $request->get_json_params();

    $requestId = $data['request_id'];
    $delayMinutes = $data['delay_minutes'];
    $postSessionBuffer = $data['post_session_buffer'];

    // Call service to reschedule
    $callRequestService = get_call_request_service();

    $newRequest = $callRequestService->reschedule(
        originalRequestId: $requestId,
        delayMinutes: $delayMinutes ?? 10, // Fallback
        postSessionBufferMinutes: $postSessionBuffer
    );

    return new WP_REST_Response([
        'new_request_id' => $newRequest->id,
        'scheduled_for' => $newRequest->scheduledExecutionTime->format('c'),
        'status' => 'rescheduled'
    ]);
}
```

#### Step 5.4: Reschedule Creates New CallRequest

**File**: `src/Core/CallRequestService.php`

**Method**: `reschedule()`

**What happens inside**:
```php
public function reschedule(
    string $originalRequestId,
    int $delayMinutes,
    ?int $postSessionBufferMinutes = null
): CallRequest {
    // 1. Load original request
    $original = $this->repository->findById($originalRequestId);

    if ($original === null) {
        throw new \RuntimeException("CallRequest not found: {$originalRequestId}");
    }

    $now = new DateTimeImmutable();

    // 2. Calculate scheduled execution time
    if ($postSessionBufferMinutes !== null) {
        // "After current session" option
        // Need to look at consultant presence for estimated end time
        $presence = $this->presenceRepository->getPresence($original->sellerId);

        if ($presence === null || $presence->estimatedSessionEnd === null) {
            // Fallback: use simple delay if no session info
            $scheduledTime = $now->modify("+{$delayMinutes} minutes");
        } else {
            // Use estimated session end + buffer
            $scheduledTime = $presence->estimatedSessionEnd->modify("+{$postSessionBufferMinutes} minutes");
        }
    } else {
        // Fixed delay: now + X minutes
        $scheduledTime = $now->modify("+{$delayMinutes} minutes");
    }

    // 3. New initiation window = scheduled time + small buffer
    $newInitiationWindowStart = $scheduledTime;
    $newInitiationWindowEnd = $scheduledTime->modify('+5 minutes');

    // 4. Generate new request ID
    $newRequestId = 'req_' . bin2hex(random_bytes(16));

    // 5. Create new CallRequest (copies all details from original)
    $newRequest = new CallRequest(
        id: $newRequestId,
        checkoutSessionId: $original->checkoutSessionId, // Same checkout
        sellerId: $original->sellerId,
        buyerId: $original->buyerId,
        buyerPhone: $original->buyerPhone,
        buyerEmail: $original->buyerEmail,
        productId: $original->productId,
        sku: $original->sku,
        pricingModel: $original->pricingModel,           // Same pricing
        agreedPrice: $original->agreedPrice,             // Same price
        currency: $original->currency,
        prepaidMinutes: $original->prepaidMinutes,
        createdAt: $now,                                 // NEW creation time
        initiationWindowStart: $newInitiationWindowStart,
        initiationWindowEnd: $newInitiationWindowEnd,    // NEW window
        callDurationMinutes: $original->callDurationMinutes,
        scheduledExecutionTime: $scheduledTime,          // NEW scheduled time
        status: CallRequestStatus::SCHEDULED,            // NEW status
        actualCallStartTime: null,
        actualCallEndTime: null,
        actualCallDurationMinutes: null,
        callCompletedSuccessfully: false,
        correlationId: $original->correlationId,
        referrerUrl: $original->referrerUrl,
        supersededByRequestId: null,
        supersededRequestId: $original->id               // Links back to original
    );

    // 6. Mark original as SUPERSEDED
    $supersededOriginal = new CallRequest(
        id: $original->id,
        // ... all original fields ...
        status: CallRequestStatus::SUPERSEDED,           // CHANGED
        supersededByRequestId: $newRequest->id,          // Links forward
        // ... rest unchanged ...
    );

    // 7. Save both
    $this->repository->save($supersededOriginal);
    $this->repository->save($newRequest);

    // 8. Log rescheduling
    $this->logger->info('dlme.call_request.rescheduled', [
        'original_request_id' => $original->id,
        'new_request_id' => $newRequest->id,
        'delay_minutes' => $delayMinutes,
        'scheduled_execution_time' => $scheduledTime->format('c'),
        'seller_id' => $original->sellerId,
    ]);

    return $newRequest;
}
```

**Key Points**:
- Original CallRequest marked as `SUPERSEDED`
- New CallRequest created with status `SCHEDULED`
- Both link to each other (bidirectional relationship)
- Same pricing, product, buyer info (frozen snapshot)
- New scheduled time calculated from delay

**Key Classes**:
- `CallRequestService` - Handles rescheduling
- `ConsultantPresenceRepository` - Used for "after session" calculation
- `CallRequest` - Immutable, so must create new instance

**Logged Event**: `dlme.call_request.rescheduled`

---

## Phase 6: Scheduled Callback Execution

### User Experience
10 minutes pass. Behind the scenes, a cron job finds the scheduled CallRequest and sends it to Asterisk. Both consultant and client receive phone calls and are connected.

### Code Flow

#### Step 6.1: Cron Job Finds Ready Requests

**Full-scope agent (cron job)**:
```php
// wp-cron or system cron calls this function every minute
add_action('dlme_execute_scheduled_callbacks', 'dlme_process_scheduled_callbacks');

function dlme_process_scheduled_callbacks() {
    $callRequestRepository = get_call_request_repository();
    $callExecutionService = get_call_execution_service();

    // 1. Find all requests ready for execution
    $now = new DateTimeImmutable();
    $readyRequests = $callRequestRepository->findReadyForExecution($now);

    foreach ($readyRequests as $request) {
        // 2. Validate can execute
        if (!$callExecutionService->canExecuteNow($request->id)) {
            continue; // Skip if not ready
        }

        // 3. Build execution payload
        $payload = $callExecutionService->buildExecutionPayload($request->id);

        // 4. Send to Asterisk
        $response = wp_remote_post('https://asterisk.example.com/api/initiate-call', [
            'body' => json_encode($payload->toArray()),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            error_log("Failed to send call to Asterisk: " . $response->get_error_message());
            continue;
        }

        // 5. Mark as scheduled (sent to Asterisk)
        $callExecutionService->markAsScheduled($request->id);
    }
}

// Register cron event
if (!wp_next_scheduled('dlme_execute_scheduled_callbacks')) {
    wp_schedule_event(time(), 'every_minute', 'dlme_execute_scheduled_callbacks');
}
```

#### Step 6.2: Repository Finds Ready Requests

**File**: `src/Core/CallRequestRepository.php` (interface)

**Method**: `findReadyForExecution()`

**Full-scope implementation**:
```php
class WpCallRequestRepository implements CallRequestRepository
{
    public function findReadyForExecution(DateTimeInterface $asOf): array
    {
        global $wpdb;

        // Query for SCHEDULED requests where scheduled_execution_time <= now
        $sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dlme_call_requests
             WHERE status = %s
             AND scheduled_execution_time IS NOT NULL
             AND scheduled_execution_time <= %s
             ORDER BY scheduled_execution_time ASC
             LIMIT 100",
            CallRequestStatus::SCHEDULED->value,
            $asOf->format('Y-m-d H:i:s')
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        // Hydrate CallRequest objects
        return array_map([$this, 'hydrateCallRequest'], $rows);
    }
}
```

#### Step 6.3: Build Execution Payload for Asterisk

**File**: `src/Core/CallExecutionService.php`

**Method**: `buildExecutionPayload()`

**What happens inside**:
```php
public function buildExecutionPayload(string $requestId): CallExecutionPayload
{
    // 1. Load request
    $request = $this->repository->findById($requestId);

    if ($request === null) {
        throw new \RuntimeException("CallRequest not found: {$requestId}");
    }

    // 2. Build payload with all necessary info for Asterisk
    $payload = new CallExecutionPayload(
        callRequestId: $request->id,
        consultantPhone: '+1000000000', // TODO: Get from consultant profile
        clientPhone: $request->buyerPhone,
        durationLimitMinutes: $request->callDurationMinutes,
        pricingModel: $request->pricingModel->value,
        correlationId: $request->correlationId,
        scheduledExecutionTime: $request->scheduledExecutionTime,
        metadata: [
            'checkout_session_id' => $request->checkoutSessionId,
            'seller_id' => $request->sellerId,
            'buyer_id' => $request->buyerId,
            'sku' => $request->sku,
            'agreed_price' => $request->agreedPrice,
            'currency' => $request->currency,
        ]
    );

    // 3. Log
    $this->logger->info('dlme.call_execution.payload_built', [
        'call_request_id' => $requestId,
        'pricing_model' => $request->pricingModel->value,
        'duration_limit_minutes' => $request->callDurationMinutes,
    ]);

    return $payload;
}
```

**Key Classes**:
- `CallExecutionPayload` - Value object sent to Asterisk
- Contains: phones, duration limit, pricing model, all metadata

**Logged Event**: `dlme.call_execution.payload_built`

#### Step 6.4: Asterisk Receives Payload and Initiates Call

**Asterisk FastAGI or REST API** (external system, not our code):
```python
# Pseudo-code for Asterisk side
@app.post('/api/initiate-call')
def initiate_call(payload):
    call_request_id = payload['call_request_id']
    consultant_phone = payload['consultant_phone']
    client_phone = payload['client_phone']
    duration_limit = payload['duration_limit_minutes']

    # 1. Call consultant
    consultant_channel = asterisk.originate(
        channel=f'PJSIP/{consultant_phone}',
        context='outgoing',
        exten='connect-call',
        priority=1,
        variables={'call_request_id': call_request_id}
    )

    # 2. When consultant answers, call client
    if consultant_channel.answered:
        client_channel = asterisk.originate(
            channel=f'PJSIP/{client_phone}',
            context='outgoing',
            exten='connect-call',
            priority=1
        )

        # 3. Bridge the two channels
        if client_channel.answered:
            bridge = asterisk.create_bridge()
            bridge.add_channel(consultant_channel)
            bridge.add_channel(client_channel)

            # 4. Set timer for duration limit
            asterisk.schedule_hangup(bridge, duration_limit * 60)

            # 5. Monitor call events
            bridge.on('destroyed', lambda: send_completion_webhook(call_request_id))

    return {'status': 'initiated'}
```

---

## Phase 7: Call Completion

### User Experience
The call ends (consultant or client hangs up, or duration limit reached). Asterisk sends a webhook to WordPress with call details.

### Code Flow

#### Step 7.1: Asterisk Sends Completion Webhook

**Asterisk** (external system):
```python
def send_completion_webhook(call_request_id, call_details):
    webhook_url = 'https://site.com/wp-json/dlme/v1/call-completion'

    payload = {
        'call_request_id': call_request_id,
        'status': 'completed',  # or 'failed', 'no_answer'
        'started_at': call_details.start_time.isoformat(),
        'ended_at': call_details.end_time.isoformat(),
        'duration_minutes': call_details.duration_minutes,
        'consultant_answered': call_details.consultant_answered,
        'client_answered': call_details.client_answered,
        'disconnect_reason': 'normal'  # or 'consultant_hangup', 'timeout', etc.
    }

    requests.post(webhook_url, json=payload)
```

#### Step 7.2: WordPress Receives Completion Webhook

**Full-scope agent endpoint**:
```php
add_action('rest_api_init', function() {
    register_rest_route('dlme/v1', '/call-completion', [
        'methods' => 'POST',
        'callback' => 'dlme_handle_call_completion',
        'permission_callback' => 'dlme_verify_asterisk_signature',
    ]);
});

function dlme_handle_call_completion(WP_REST_Request $request) {
    $data = $request->get_json_params();

    // 1. Parse webhook payload
    $event = CallCompletionEvent::fromArray($data);

    // 2. Handle completion
    $callCompletionService = get_call_completion_service();
    $completedRequest = $callCompletionService->handleCompletion($event);

    if ($completedRequest === null) {
        return new WP_REST_Response([
            'error' => 'call_request_not_found'
        ], 404);
    }

    // 3. For PAYG, we now have actual duration for billing
    if ($completedRequest->pricingModel === PricingModel::PAY_AS_YOU_GO) {
        // Trigger billing/invoicing
        do_action('dlme_bill_payg_call', $completedRequest);
    }

    return new WP_REST_Response(['status' => 'processed']);
}
```

#### Step 7.3: Process Completion Event

**File**: `src/Core/CallCompletionService.php`

**Method**: `handleCompletion()`

**What happens inside**:
```php
public function handleCompletion(CallCompletionEvent $event): ?CallRequest
{
    // 1. Log receipt of completion
    $this->logger->info('dlme.call_completion.received', [
        'call_request_id' => $event->callRequestId,
        'status' => $event->status,
        'duration_minutes' => $event->durationMinutes,
        'consultant_answered' => $event->consultantAnswered,
        'client_answered' => $event->clientAnswered,
    ]);

    // 2. Load the request
    $request = $this->repository->findById($event->callRequestId);

    if ($request === null) {
        // Request not found
        $this->logger->error('dlme.call_completion.request_not_found', [
            'call_request_id' => $event->callRequestId,
        ]);
        return null;
    }

    // 3. Determine final status
    $finalStatus = match ($event->status) {
        'completed' => CallRequestStatus::COMPLETED,
        'failed', 'no_answer' => CallRequestStatus::FAILED,
        default => CallRequestStatus::FAILED,
    };

    // 4. Create updated request with actual metrics
    $completed = new CallRequest(
        id: $request->id,
        // ... all existing fields ...
        status: $finalStatus,                             // CHANGED
        actualCallStartTime: $event->startedAt,           // NEW
        actualCallEndTime: $event->endedAt,               // NEW
        actualCallDurationMinutes: $event->durationMinutes, // NEW - for PAYG
        callCompletedSuccessfully: $event->status === 'completed', // NEW
        // ... rest unchanged ...
    );

    // 5. Save updated request
    $this->repository->save($completed);

    // 6. Log completion
    $this->logger->info('dlme.call_completion.processed', [
        'call_request_id' => $request->id,
        'final_status' => $finalStatus->value,
        'actual_duration_minutes' => $event->durationMinutes,
        'pricing_model' => $request->pricingModel->value,
    ]);

    return $completed;
}
```

**Key Points**:
- Updates CallRequest with actual call metrics
- `actualCallDurationMinutes` is critical for PAYG billing
- Status transitions to `COMPLETED` or `FAILED`
- CallRequest now has complete lifecycle

**Key Classes**:
- `CallCompletionService` - Processes completion webhooks
- `CallCompletionEvent` - Value object from Asterisk webhook

**Logged Events**:
- `dlme.call_completion.received` (info)
- `dlme.call_completion.request_not_found` (error - if not found)
- `dlme.call_completion.processed` (info)

#### Step 7.4: Optional Post-Completion Actions

**Full-scope agent can hook into**:
```php
// Billing for PAYG
add_action('dlme_bill_payg_call', function(CallRequest $request) {
    if ($request->pricingModel !== PricingModel::PAY_AS_YOU_GO) {
        return;
    }

    // Calculate actual charge based on actual duration
    $ratePerMinute = get_rate_from_sku($request->sku);
    $actualCharge = $ratePerMinute * $request->actualCallDurationMinutes;
    $maxCharge = (float) $request->agreedPrice;

    // Cap at pre-authorized amount
    $finalCharge = min($actualCharge, $maxCharge);

    // Create invoice or charge
    create_woocommerce_invoice($request->buyerId, $finalCharge, $request->id);
});

// Send follow-up emails
add_action('dlme_call_completed', function(CallRequest $request) {
    if ($request->callCompletedSuccessfully) {
        // Send thank you email to client
        wp_mail(
            $request->buyerEmail,
            'Thank you for your consultation',
            'Your call with the consultant has completed...'
        );

        // Prompt for review
        send_review_request($request->buyerId, $request->sellerId);
    }
});
```

---

## Data Flow Summary

### Complete Entity Lifecycle

```
1. ButtonClickContext
   ↓
2. CheckoutSession [status: PENDING]
   ↓
3. PaymentDetails (from Stripe webhook)
   ↓
4. CheckoutSession [status: SUCCEEDED]
   ↓
5. CallRequest [status: PENDING] - created via PostPaymentWorkflow
   ↓
   [Branch A: Immediate Call]
   ↓
6a. CallExecutionPayload → Asterisk
   ↓
7a. CallRequest [status: IN_PROGRESS]

   [Branch B: Capture for Later]
   ↓
6b. Original CallRequest [status: SUPERSEDED]
   ↓
7b. New CallRequest [status: SCHEDULED]
   ↓
8b. Cron finds scheduled request
   ↓
9b. CallExecutionPayload → Asterisk
   ↓
10b. CallRequest [status: IN_PROGRESS]

   [Both branches converge]
   ↓
11. Call completes → CallCompletionEvent
   ↓
12. CallRequest [status: COMPLETED]
    - with actualCallDurationMinutes
    - with actualCallStartTime/EndTime
```

### Key State Transitions

**CheckoutSession**:
- `PENDING` → `SUCCEEDED` (on payment)
- `PENDING` → `FAILED` (on payment failure)

**CallRequest**:
- `PENDING` → `SCHEDULED` (when sent to Asterisk)
- `PENDING` → `SUPERSEDED` (when rescheduled)
- `SCHEDULED` → `IN_PROGRESS` (when call starts)
- `SCHEDULED` → `EXPIRED` (when initiation window passes)
- `IN_PROGRESS` → `COMPLETED` (on successful completion)
- `IN_PROGRESS` → `FAILED` (on call failure)

### Core Services Interaction Map

```
CallToActionService
    ↓ (queries)
AvailabilityService
    ↓ (uses)
SellerAvailabilityRepository

CheckoutService
    ↓ (triggers)
PostPaymentWorkflow
    ↓ (calls)
CallRequestService
    ↓ (uses)
ProductMetadataProvider + CallRequestRepository

CallRequestService.reschedule()
    ↓ (queries)
ConsultantPresenceService
    ↓ (uses)
ConsultantPresenceRepository

CallExecutionService
    ↓ (builds payloads)
CallExecutionPayload
    ↓ (sent to)
Asterisk/FastAGI

CallCompletionService
    ↓ (receives)
CallCompletionEvent
    ↓ (updates)
CallRequestRepository
```

---

## Developer Checklist

When debugging or extending this system, trace through:

1. **Availability Check**:
   - [ ] `AvailabilityService::isSellerAvailableNow()`
   - [ ] `SellerAvailabilityRepository::getAvailableNowFlag()`

2. **CTA Decision**:
   - [ ] `CallToActionService::decideForSellerLanding()`
   - [ ] `CtaStyleConfig` and `CtaEnabledPolicy`

3. **Checkout Flow**:
   - [ ] `CheckoutService::startFromClick()`
   - [ ] `ButtonClickContext` captures click metadata
   - [ ] `CheckoutSession` created with buyer contact

4. **Payment**:
   - [ ] Stripe webhook → `CheckoutService::markPaymentSuccessful()`
   - [ ] `PostPaymentWorkflow::onPaymentConfirmed()` hook fires

5. **CallRequest Creation**:
   - [ ] `CallRequestService::createFromCheckoutSession()`
   - [ ] `ProductMetadataProvider` provides timing/pricing config
   - [ ] `CallRequest` entity persisted

6. **Rescheduling**:
   - [ ] Dashboard UI shows pending requests
   - [ ] `CallRequestService::reschedule()`
   - [ ] New `CallRequest` created, original marked `SUPERSEDED`

7. **Execution**:
   - [ ] Cron finds ready requests via `CallRequestRepository::findReadyForExecution()`
   - [ ] `CallExecutionService::buildExecutionPayload()`
   - [ ] Payload sent to Asterisk

8. **Completion**:
   - [ ] Asterisk webhook → `CallCompletionService::handleCompletion()`
   - [ ] `CallRequest` updated with actual metrics
   - [ ] Status changed to `COMPLETED` or `FAILED`

---

## Common Debug Scenarios

### "Button shows wrong label"
→ Check `CallToActionService::decideForSellerLanding()`
→ Verify `AvailabilityService::getAvailabilityStatus()`
→ Check manual "available now" flag in repository

### "Payment succeeded but no CallRequest created"
→ Check if `PostPaymentWorkflow` is registered in CheckoutService
→ Verify `CallRequestService::createFromCheckoutSession()` is being called
→ Check logs for `dlme.call_request.created` event

### "Rescheduled call never executes"
→ Check if cron job is running
→ Verify `CallRequestRepository::findReadyForExecution()` returns the request
→ Check `scheduledExecutionTime` is in the past
→ Verify request status is `SCHEDULED`, not `PENDING`

### "Call duration not recorded for PAYG"
→ Check Asterisk webhook is sending to correct endpoint
→ Verify `CallCompletionEvent` has `durationMinutes` populated
→ Check `CallCompletionService::handleCompletion()` is updating `actualCallDurationMinutes`

### "Original request not marked as superseded"
→ Check `CallRequestService::reschedule()` is saving both requests
→ Verify `supersededByRequestId` is set on original
→ Verify `supersededRequestId` is set on new request

---

**End of Developer Flow Guide**

For architecture and design decisions, see: `agent_journal.md`
For integration guide for full-scope agent, see: `HANDOFF.md Part 3`
