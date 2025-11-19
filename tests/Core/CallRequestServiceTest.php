<?php

declare(strict_types=1);

namespace Tests\Core;

use DateTimeImmutable;
use DLme\Core\ArrayLogger;
use DLme\Core\AvailabilityStatus;
use DLme\Core\CallRequestService;
use DLme\Core\CallRequestStatus;
use DLme\Core\CheckoutSession;
use DLme\Core\CheckoutSessionStatus;
use DLme\Core\ConsultantPresence;
use DLme\Core\CtaType;
use DLme\Core\InMemoryCallRequestRepository;
use DLme\Core\InMemoryConsultantPresenceRepository;
use DLme\Core\InMemoryProductMetadataProvider;
use DLme\Core\PageType;
use DLme\Core\PricingModel;
use DLme\Core\WorkflowContext;

beforeEach(function () {
    $this->repository = new InMemoryCallRequestRepository();
    $this->presenceRepo = new InMemoryConsultantPresenceRepository();
    $this->productMetadata = new InMemoryProductMetadataProvider();
    $this->logger = new ArrayLogger();

    $this->service = new CallRequestService(
        $this->repository,
        $this->presenceRepo,
        $this->productMetadata,
        $this->logger
    );

    // Setup default product metadata
    $this->productMetadata->setProduct(
        sku: 'PROD-001',
        pricingModel: PricingModel::PAY_AS_YOU_GO,
        callDurationMinutes: 30,
        initiationWindowMinutes: 15,
        prepaidMinutes: null
    );
});

describe('CallRequestService - createFromCheckoutSession', function () {
    test('creates call request from checkout session', function () {
        $session = new CheckoutSession(
            id: 'sess_xyz789',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 50.00,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: 'https://example.com',
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::SUCCEEDED,
            buyerPhone: '+1234567890',
            buyerEmail: 'buyer@example.com'
        );

        $request = $this->service->createFromCheckoutSession($session);

        expect($request->checkoutSessionId)->toBe('sess_xyz789');
        expect($request->sellerId)->toBe(123);
        expect($request->buyerId)->toBe(456);
        expect($request->buyerPhone)->toBe('+1234567890');
        expect($request->buyerEmail)->toBe('buyer@example.com');
        expect($request->productId)->toBe(789);
        expect($request->sku)->toBe('PROD-001');
        expect($request->pricingModel)->toBe(PricingModel::PAY_AS_YOU_GO);
        expect($request->agreedPrice)->toBe('50.00');
        expect($request->currency)->toBe('USD');
        expect($request->callDurationMinutes)->toBe(30);
        expect($request->status)->toBe(CallRequestStatus::PENDING);
        expect($request->correlationId)->toBe('corr-123');
        expect($request->referrerUrl)->toBe('https://example.com');
    });

    test('generates request ID with req_ prefix', function () {
        $session = new CheckoutSession(
            id: 'sess_xyz789',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 50.00,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: null,
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::SUCCEEDED,
            buyerPhone: '+1234567890',
            buyerEmail: null
        );

        $request = $this->service->createFromCheckoutSession($session);

        expect($request->id)->toStartWith('req_');
    });

    test('sets initiation window from product metadata', function () {
        $session = new CheckoutSession(
            id: 'sess_xyz789',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 50.00,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: null,
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::SUCCEEDED,
            buyerPhone: '+1234567890',
            buyerEmail: null
        );

        $request = $this->service->createFromCheckoutSession($session);

        // initiation window should be now + 15 minutes (from product metadata)
        $expected = (new DateTimeImmutable())->modify('+15 minutes');

        expect($request->initiationWindowEnd->getTimestamp())
            ->toBeGreaterThanOrEqual($expected->getTimestamp() - 5)
            ->toBeLessThanOrEqual($expected->getTimestamp() + 5);
    });

    test('saves request to repository', function () {
        $session = new CheckoutSession(
            id: 'sess_xyz789',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 50.00,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: null,
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::SUCCEEDED,
            buyerPhone: '+1234567890',
            buyerEmail: null
        );

        $request = $this->service->createFromCheckoutSession($session);

        $found = $this->repository->findById($request->id);

        expect($found)->toBe($request);
    });

    test('logs request creation', function () {
        $session = new CheckoutSession(
            id: 'sess_xyz789',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 50.00,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: null,
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::SUCCEEDED,
            buyerPhone: '+1234567890',
            buyerEmail: null
        );

        $this->service->createFromCheckoutSession($session);

        expect($this->logger->hasRecord('info', 'dlme.call_request.created'))->toBeTrue();
    });
});

describe('CallRequestService - reschedule', function () {
    test('creates new request superseding original', function () {
        // Create original request
        $session = new CheckoutSession(
            id: 'sess_xyz789',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 50.00,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: null,
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::SUCCEEDED,
            buyerPhone: '+1234567890',
            buyerEmail: null
        );

        $original = $this->service->createFromCheckoutSession($session);

        // Reschedule with 10 minute delay
        $new = $this->service->reschedule($original->id, delayMinutes: 10);

        expect($new->id)->not()->toBe($original->id);
        expect($new->checkoutSessionId)->toBe($original->checkoutSessionId);
        expect($new->supersededRequestId)->toBe($original->id);
        expect($new->status)->toBe(CallRequestStatus::SCHEDULED);

        // Original should be marked as superseded
        $updatedOriginal = $this->repository->findById($original->id);
        expect($updatedOriginal->status)->toBe(CallRequestStatus::SUPERSEDED);
        expect($updatedOriginal->supersededByRequestId)->toBe($new->id);
    });

    test('calculates scheduled time from delay', function () {
        $session = new CheckoutSession(
            id: 'sess_xyz789',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 50.00,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: null,
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::SUCCEEDED,
            buyerPhone: '+1234567890',
            buyerEmail: null
        );

        $original = $this->service->createFromCheckoutSession($session);
        $new = $this->service->reschedule($original->id, delayMinutes: 10);

        $expected = (new DateTimeImmutable())->modify('+10 minutes');

        expect($new->scheduledExecutionTime)->not()->toBeNull();
        expect($new->scheduledExecutionTime->getTimestamp())
            ->toBeGreaterThanOrEqual($expected->getTimestamp() - 5)
            ->toBeLessThanOrEqual($expected->getTimestamp() + 5);
    });

    test('logs rescheduling', function () {
        $session = new CheckoutSession(
            id: 'sess_xyz789',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 50.00,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: null,
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::SUCCEEDED,
            buyerPhone: '+1234567890',
            buyerEmail: null
        );

        $original = $this->service->createFromCheckoutSession($session);

        $this->logger->clear();

        $this->service->reschedule($original->id, delayMinutes: 10);

        expect($this->logger->hasRecord('info', 'dlme.call_request.rescheduled'))->toBeTrue();
    });
});

describe('CallRequestService - markExpired', function () {
    test('marks request as expired', function () {
        $session = new CheckoutSession(
            id: 'sess_xyz789',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 50.00,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: null,
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::SUCCEEDED,
            buyerPhone: '+1234567890',
            buyerEmail: null
        );

        $request = $this->service->createFromCheckoutSession($session);

        $this->service->markExpired($request->id);

        $updated = $this->repository->findById($request->id);

        expect($updated->status)->toBe(CallRequestStatus::EXPIRED);
    });

    test('logs expiry', function () {
        $session = new CheckoutSession(
            id: 'sess_xyz789',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 50.00,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: null,
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::SUCCEEDED,
            buyerPhone: '+1234567890',
            buyerEmail: null
        );

        $request = $this->service->createFromCheckoutSession($session);

        $this->logger->clear();

        $this->service->markExpired($request->id);

        expect($this->logger->hasRecord('info', 'dlme.call_request.expired'))->toBeTrue();
    });
});
