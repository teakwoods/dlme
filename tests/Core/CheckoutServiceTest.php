<?php

declare(strict_types=1);

use DLme\Core\AvailabilityStatus;
use DLme\Core\ButtonClickContext;
use DLme\Core\CheckoutService;
use DLme\Core\CheckoutSession;
use DLme\Core\CheckoutSessionStatus;
use DLme\Core\CtaType;
use DLme\Core\InMemoryCheckoutSessionRepository;
use DLme\Core\PageType;
use DLme\Core\PaymentDetails;
use DLme\Core\PostPaymentWorkflow;
use DLme\Core\WorkflowContext;

beforeEach(function () {
    $this->repository = new InMemoryCheckoutSessionRepository();
    $this->service = new CheckoutService($this->repository);
});

describe('CheckoutService - startFromClick', function () {
    test('creates new pending session', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: 456,
            pageType: PageType::PRODUCT_PAGE,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: 'PROD-001',
            price: 99.99,
            referrerUrl: 'https://example.com',
            buyerId: 789,
            correlationId: 'corr-123'
        );

        $workflow = new WorkflowContext('instant_call', ['priority' => 'high']);

        $session = $this->service->startFromClick($click, 'idem-key-1', $workflow);

        expect($session->status)->toBe(CheckoutSessionStatus::PENDING);
        expect($session->idempotencyKey)->toBe('idem-key-1');
        expect($session->sellerId)->toBe(123);
        expect($session->productId)->toBe(456);
        expect($session->buyerId)->toBe(789);
        expect($session->sku)->toBe('PROD-001');
        expect($session->quotedPrice)->toBe(99.99);
        expect($session->pageType)->toBe(PageType::PRODUCT_PAGE);
        expect($session->ctaType)->toBe(CtaType::INSTANT_CHECKOUT);
        expect($session->availabilityStatus)->toBe(AvailabilityStatus::AVAILABLE_NOW);
        expect($session->referrerUrl)->toBe('https://example.com');
        expect($session->correlationId)->toBe('corr-123');
        expect($session->workflowContext->workflowType)->toBe('instant_call');
        expect($session->workflowContext->attributes)->toBe(['priority' => 'high']);
        expect($session->id)->toStartWith('sess_');
    });

    test('is idempotent - returns existing session', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: 456,
            pageType: PageType::PRODUCT_PAGE,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,
            price: null,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflow = new WorkflowContext('instant_call', []);

        $session1 = $this->service->startFromClick($click, 'idem-key-1', $workflow);
        $session2 = $this->service->startFromClick($click, 'idem-key-1', $workflow);

        expect($session2->id)->toBe($session1->id);
        expect($session2->status)->toBe($session1->status);
    });

    test('returns existing session even if succeeded', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: null,
            pageType: PageType::SELLER_LANDING,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,
            price: null,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflow = new WorkflowContext('instant_call', []);

        $session = $this->service->startFromClick($click, 'idem-key-1', $workflow);

        // Mark as succeeded
        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: 'txn_123',
            paymentInstrumentRef: null,
            currency: 'USD',
            amount: 99.99,
            paidAt: new DateTimeImmutable()
        );
        $this->repository->markSucceededWithPayment('idem-key-1', $payment);

        // Try to start again with same key
        $session2 = $this->service->startFromClick($click, 'idem-key-1', $workflow);

        expect($session2->id)->toBe($session->id);
        expect($session2->status)->toBe(CheckoutSessionStatus::SUCCEEDED);
    });

    test('captures buyer contact information when provided', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: 456,
            pageType: PageType::PRODUCT_PAGE,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: 'PROD-001',
            price: 99.99,
            referrerUrl: 'https://example.com',
            buyerId: 789,
            correlationId: 'corr-123',
            buyerPhone: '+15551234567',
            buyerEmail: 'buyer@example.com'
        );

        $workflow = new WorkflowContext('instant_call', []);

        $session = $this->service->startFromClick($click, 'idem-key-contact', $workflow);

        expect($session->buyerPhone)->toBe('+15551234567');
        expect($session->buyerEmail)->toBe('buyer@example.com');
    });
});

describe('CheckoutService - buildPaymentHandoffPayload', function () {
    test('builds payload with all metadata', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: 456,
            pageType: PageType::PRODUCT_PAGE,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: 'PROD-001',
            price: 99.99,
            referrerUrl: 'https://example.com/ref',
            buyerId: 789,
            correlationId: 'corr-456'
        );

        $workflow = new WorkflowContext('instant_call', ['priority' => 'high', 'region' => 'us-west']);

        $session = $this->service->startFromClick($click, 'idem-key-1', $workflow);
        $payload = $this->service->buildPaymentHandoffPayload($session);

        expect($payload->checkoutSessionId)->toBe($session->id);
        expect($payload->idempotencyKey)->toBe('idem-key-1');
        expect($payload->sellerId)->toBe(123);
        expect($payload->buyerId)->toBe(789);
        expect($payload->productId)->toBe(456);
        expect($payload->sku)->toBe('PROD-001');
        expect($payload->quotedPrice)->toBe(99.99);

        // Check metadata
        expect($payload->metadata['dlme_session_id'])->toBe($session->id);
        expect($payload->metadata['dlme_idempotency_key'])->toBe('idem-key-1');
        expect($payload->metadata['dlme_seller_id'])->toBe('123');
        expect($payload->metadata['dlme_product_id'])->toBe('456');
        expect($payload->metadata['dlme_buyer_id'])->toBe('789');
        expect($payload->metadata['dlme_sku'])->toBe('PROD-001');
        expect($payload->metadata['dlme_cta_type'])->toBe('instant_checkout');
        expect($payload->metadata['dlme_page_type'])->toBe('product_page');
        expect($payload->metadata['dlme_availability_status'])->toBe('available_now');
        expect($payload->metadata['dlme_referrer_url'])->toBe('https://example.com/ref');
        expect($payload->metadata['dlme_correlation_id'])->toBe('corr-456');
        expect($payload->metadata['dlme_workflow_type'])->toBe('instant_call');
        expect($payload->metadata['dlme_workflow_priority'])->toBe('high');
        expect($payload->metadata['dlme_workflow_region'])->toBe('us-west');
    });

    test('omits null optional fields from metadata', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: null,
            pageType: PageType::SELLER_LANDING,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,
            price: null,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflow = new WorkflowContext('instant_call', []);

        $session = $this->service->startFromClick($click, 'idem-key-1', $workflow);
        $payload = $this->service->buildPaymentHandoffPayload($session);

        expect($payload->metadata)->not->toHaveKey('dlme_product_id');
        expect($payload->metadata)->not->toHaveKey('dlme_buyer_id');
        expect($payload->metadata)->not->toHaveKey('dlme_sku');
        expect($payload->metadata)->not->toHaveKey('dlme_referrer_url');
        expect($payload->metadata)->not->toHaveKey('dlme_correlation_id');

        // Required fields still present
        expect($payload->metadata)->toHaveKey('dlme_session_id');
        expect($payload->metadata)->toHaveKey('dlme_seller_id');
    });
});

describe('CheckoutService - markPaymentSuccessful', function () {
    test('transitions pending to succeeded', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: 456,
            pageType: PageType::PRODUCT_PAGE,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,
            price: 99.99,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflow = new WorkflowContext('instant_call', []);
        $session = $this->service->startFromClick($click, 'idem-key-1', $workflow);

        expect($session->status)->toBe(CheckoutSessionStatus::PENDING);

        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: 'txn_123',
            paymentInstrumentRef: 'card_456',
            currency: 'USD',
            amount: 99.99,
            paidAt: new DateTimeImmutable()
        );

        $updated = $this->service->markPaymentSuccessful('idem-key-1', $payment);

        expect($updated)->not->toBeNull();
        expect($updated->status)->toBe(CheckoutSessionStatus::SUCCEEDED);
        expect($updated->externalTransactionId)->toBe('txn_123');
        expect($updated->completedAt)->not->toBeNull();
    });

    test('is idempotent for already succeeded session', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: null,
            pageType: PageType::SELLER_LANDING,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,
            price: null,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflow = new WorkflowContext('instant_call', []);
        $session = $this->service->startFromClick($click, 'idem-key-1', $workflow);

        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: 'txn_123',
            paymentInstrumentRef: null,
            currency: 'USD',
            amount: 99.99,
            paidAt: new DateTimeImmutable()
        );

        $updated1 = $this->service->markPaymentSuccessful('idem-key-1', $payment);
        $updated2 = $this->service->markPaymentSuccessful('idem-key-1', $payment);

        expect($updated1->status)->toBe(CheckoutSessionStatus::SUCCEEDED);
        expect($updated2->status)->toBe(CheckoutSessionStatus::SUCCEEDED);
        expect($updated2->id)->toBe($updated1->id);
    });

    test('calls PostPaymentWorkflow on success', function () {
        $callCount = 0;
        $capturedSession = null;
        $capturedPayment = null;

        $workflow = new class($callCount, $capturedSession, $capturedPayment) implements PostPaymentWorkflow {
            public function __construct(
                private int &$callCount,
                private ?CheckoutSession &$capturedSession,
                private ?PaymentDetails &$capturedPayment
            ) {}

            public function onPaymentConfirmed(CheckoutSession $session, PaymentDetails $payment): void
            {
                $this->callCount++;
                $this->capturedSession = $session;
                $this->capturedPayment = $payment;
            }
        };

        $service = new CheckoutService($this->repository, $workflow);

        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: null,
            pageType: PageType::SELLER_LANDING,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,
            price: null,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflowContext = new WorkflowContext('instant_call', []);
        $session = $service->startFromClick($click, 'idem-key-1', $workflowContext);

        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: 'txn_123',
            paymentInstrumentRef: null,
            currency: 'USD',
            amount: 99.99,
            paidAt: new DateTimeImmutable()
        );

        $service->markPaymentSuccessful('idem-key-1', $payment);

        expect($callCount)->toBe(1);
        expect($capturedSession)->not->toBeNull();
        expect($capturedSession->status)->toBe(CheckoutSessionStatus::SUCCEEDED);
        expect($capturedPayment->externalTransactionId)->toBe('txn_123');
    });

    test('calls PostPaymentWorkflow on every call but repository prevents duplicate transitions', function () {
        $callCount = 0;

        $workflow = new class($callCount) implements PostPaymentWorkflow {
            public function __construct(private int &$callCount) {}

            public function onPaymentConfirmed(CheckoutSession $session, PaymentDetails $payment): void
            {
                $this->callCount++;
            }
        };

        $service = new CheckoutService($this->repository, $workflow);

        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: null,
            pageType: PageType::SELLER_LANDING,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,
            price: null,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflowContext = new WorkflowContext('instant_call', []);
        $service->startFromClick($click, 'idem-key-1', $workflowContext);

        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: 'txn_123',
            paymentInstrumentRef: null,
            currency: 'USD',
            amount: 99.99,
            paidAt: new DateTimeImmutable()
        );

        // First call - should invoke workflow
        $service->markPaymentSuccessful('idem-key-1', $payment);
        expect($callCount)->toBe(1);

        // Second call - workflow gets called again (per spec answer #4: "B")
        $service->markPaymentSuccessful('idem-key-1', $payment);
        expect($callCount)->toBe(2);
    });

    test('returns null if session not found', function () {
        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: 'txn_123',
            paymentInstrumentRef: null,
            currency: 'USD',
            amount: 99.99,
            paidAt: new DateTimeImmutable()
        );

        $result = $this->service->markPaymentSuccessful('non-existent-key', $payment);

        expect($result)->toBeNull();
    });
});

describe('CheckoutService - markPaymentFailed', function () {
    test('transitions pending to failed', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: null,
            pageType: PageType::SELLER_LANDING,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,
            price: null,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflow = new WorkflowContext('instant_call', []);
        $session = $this->service->startFromClick($click, 'idem-key-1', $workflow);

        $updated = $this->service->markPaymentFailed('idem-key-1', 'Card declined');

        expect($updated)->not->toBeNull();
        expect($updated->status)->toBe(CheckoutSessionStatus::FAILED);
        expect($updated->failureReason)->toBe('Card declined');
        expect($updated->completedAt)->not->toBeNull();
    });

    test('does not resurrect succeeded session', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: null,
            pageType: PageType::SELLER_LANDING,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,
            price: null,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflow = new WorkflowContext('instant_call', []);
        $session = $this->service->startFromClick($click, 'idem-key-1', $workflow);

        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: 'txn_123',
            paymentInstrumentRef: null,
            currency: 'USD',
            amount: 99.99,
            paidAt: new DateTimeImmutable()
        );

        $this->service->markPaymentSuccessful('idem-key-1', $payment);
        $failed = $this->service->markPaymentFailed('idem-key-1', 'Too late');

        expect($failed->status)->toBe(CheckoutSessionStatus::SUCCEEDED);
        expect($failed->failureReason)->toBeNull();
    });

    test('returns null if session not found', function () {
        $result = $this->service->markPaymentFailed('non-existent-key', 'Error');

        expect($result)->toBeNull();
    });
});

describe('CheckoutService - paymentMatchesQuote', function () {
    test('returns true when amounts match exactly', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: null,
            pageType: PageType::SELLER_LANDING,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,
            price: 99.99,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflow = new WorkflowContext('instant_call', []);
        $session = $this->service->startFromClick($click, 'idem-key-1', $workflow);

        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: 'txn_123',
            paymentInstrumentRef: null,
            currency: 'USD',
            amount: 99.99,
            paidAt: new DateTimeImmutable()
        );

        expect($this->service->paymentMatchesQuote($session, $payment))->toBeTrue();
    });

    test('returns true when within tolerance', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: null,
            pageType: PageType::SELLER_LANDING,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,
            price: 100.00,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflow = new WorkflowContext('instant_call', []);
        $session = $this->service->startFromClick($click, 'idem-key-1', $workflow);

        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: 'txn_123',
            paymentInstrumentRef: null,
            currency: 'USD',
            amount: 100.005, // Within default 0.01 tolerance
            paidAt: new DateTimeImmutable()
        );

        expect($this->service->paymentMatchesQuote($session, $payment))->toBeTrue();
    });

    test('returns false when outside tolerance', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: null,
            pageType: PageType::SELLER_LANDING,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,
            price: 100.00,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflow = new WorkflowContext('instant_call', []);
        $session = $this->service->startFromClick($click, 'idem-key-1', $workflow);

        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: 'txn_123',
            paymentInstrumentRef: null,
            currency: 'USD',
            amount: 100.02, // Outside default 0.01 tolerance
            paidAt: new DateTimeImmutable()
        );

        expect($this->service->paymentMatchesQuote($session, $payment))->toBeFalse();
    });

    test('returns false when quote or amount is null', function () {
        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: null,
            pageType: PageType::SELLER_LANDING,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,
            price: null,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflow = new WorkflowContext('instant_call', []);
        $session = $this->service->startFromClick($click, 'idem-key-1', $workflow);

        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: 'txn_123',
            paymentInstrumentRef: null,
            currency: 'USD',
            amount: 100.00,
            paidAt: new DateTimeImmutable()
        );

        expect($this->service->paymentMatchesQuote($session, $payment))->toBeFalse();
    });
});
