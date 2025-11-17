<?php

declare(strict_types=1);

use DLme\Core\ArrayLogger;
use DLme\Core\AvailabilityService;
use DLme\Core\AvailabilityStatus;
use DLme\Core\ButtonClickContext;
use DLme\Core\CallToActionService;
use DLme\Core\CheckoutService;
use DLme\Core\CtaStyleConfig;
use DLme\Core\CtaType;
use DLme\Core\DefaultCtaEnabledPolicy;
use DLme\Core\InMemoryCheckoutSessionRepository;
use DLme\Core\InMemorySellerAvailabilityRepository;
use DLme\Core\InMemoryTimezoneProvider;
use DLme\Core\PageType;
use DLme\Core\PaymentDetails;
use DLme\Core\ProductContext;
use DLme\Core\WorkflowContext;

describe('AvailabilityService - Logging', function () {
    test('logs when availability flag changes to available', function () {
        $logger = new ArrayLogger();
        $repository = new InMemorySellerAvailabilityRepository();
        $timezoneProvider = new InMemoryTimezoneProvider();
        $service = new AvailabilityService($repository, $timezoneProvider, $logger);

        $service->setAvailableNow(123, true);

        expect($logger->records)->toHaveCount(1);
        expect($logger->records[0]['level'])->toBe('info');
        expect($logger->records[0]['message'])->toBe('dlme.availability.flag_changed');
        expect($logger->records[0]['context']['seller_id'])->toBe(123);
        expect($logger->records[0]['context']['availability_status'])->toBe('available_now');
        expect($logger->records[0]['context']['available'])->toBeTrue();
    });

    test('logs when availability flag changes to offline', function () {
        $logger = new ArrayLogger();
        $repository = new InMemorySellerAvailabilityRepository();
        $timezoneProvider = new InMemoryTimezoneProvider();
        $service = new AvailabilityService($repository, $timezoneProvider, $logger);

        $service->setAvailableNow(123, false);

        expect($logger->records)->toHaveCount(1);
        expect($logger->records[0]['level'])->toBe('info');
        expect($logger->records[0]['message'])->toBe('dlme.availability.flag_changed');
        expect($logger->records[0]['context']['seller_id'])->toBe(123);
        expect($logger->records[0]['context']['availability_status'])->toBe('offline');
        expect($logger->records[0]['context']['available'])->toBeFalse();
    });
});

describe('CallToActionService - Logging', function () {
    test('logs CTA decision for seller landing', function () {
        $logger = new ArrayLogger();
        $availabilityRepo = new InMemorySellerAvailabilityRepository();
        $timezoneProvider = new InMemoryTimezoneProvider();
        $availabilityService = new AvailabilityService($availabilityRepo, $timezoneProvider);
        $styleConfig = new CtaStyleConfig();
        $enabledPolicy = new DefaultCtaEnabledPolicy();
        $ctaService = new CallToActionService($availabilityService, $styleConfig, $enabledPolicy, $logger);

        $availabilityRepo->setAvailableNowFlag(123, true);

        $ctaService->decideForSellerLanding(123);

        expect($logger->records)->toHaveCount(1);
        expect($logger->records[0]['message'])->toBe('dlme.cta.decision');
        expect($logger->records[0]['context']['seller_id'])->toBe(123);
        expect($logger->records[0]['context']['page_type'])->toBe('seller_landing');
        expect($logger->records[0]['context']['availability_status'])->toBe('available_now');
        expect($logger->records[0]['context']['cta_type'])->toBe('instant_checkout');
        expect($logger->records[0]['context']['enabled'])->toBeTrue();
    });

    test('logs CTA decision for product page', function () {
        $logger = new ArrayLogger();
        $availabilityRepo = new InMemorySellerAvailabilityRepository();
        $timezoneProvider = new InMemoryTimezoneProvider();
        $availabilityService = new AvailabilityService($availabilityRepo, $timezoneProvider);
        $styleConfig = new CtaStyleConfig();
        $enabledPolicy = new DefaultCtaEnabledPolicy();
        $ctaService = new CallToActionService($availabilityService, $styleConfig, $enabledPolicy, $logger);

        $availabilityRepo->setAvailableNowFlag(123, true);
        $product = new ProductContext(456, true, 'SKU-001', 99.99);

        $ctaService->decideForProduct(123, $product);

        expect($logger->records)->toHaveCount(1);
        expect($logger->records[0]['message'])->toBe('dlme.cta.decision');
        expect($logger->records[0]['context']['seller_id'])->toBe(123);
        expect($logger->records[0]['context']['product_id'])->toBe(456);
        expect($logger->records[0]['context']['sku'])->toBe('SKU-001');
        expect($logger->records[0]['context']['page_type'])->toBe('product_page');
        expect($logger->records[0]['context']['availability_status'])->toBe('available_now');
        expect($logger->records[0]['context']['cta_type'])->toBe('instant_checkout');
        expect($logger->records[0]['context']['enabled'])->toBeTrue();
        expect($logger->records[0]['context']['is_instant_consult'])->toBeTrue();
    });

    test('filters null values from context', function () {
        $logger = new ArrayLogger();
        $availabilityRepo = new InMemorySellerAvailabilityRepository();
        $timezoneProvider = new InMemoryTimezoneProvider();
        $availabilityService = new AvailabilityService($availabilityRepo, $timezoneProvider);
        $styleConfig = new CtaStyleConfig();
        $enabledPolicy = new DefaultCtaEnabledPolicy();
        $ctaService = new CallToActionService($availabilityService, $styleConfig, $enabledPolicy, $logger);

        $availabilityRepo->setAvailableNowFlag(123, false);
        $product = new ProductContext(456, true); // No SKU or price

        $ctaService->decideForProduct(123, $product);

        expect($logger->records)->toHaveCount(1);
        expect($logger->records[0]['context'])->not->toHaveKey('sku');
        expect($logger->records[0]['context'])->toHaveKey('product_id');
    });
});

describe('CheckoutService - Logging', function () {
    test('logs session started', function () {
        $logger = new ArrayLogger();
        $repository = new InMemoryCheckoutSessionRepository();
        $service = new CheckoutService($repository, null, $logger);

        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: 456,
            pageType: PageType::PRODUCT_PAGE,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: 'SKU-001',
            price: 99.99,
            referrerUrl: 'https://example.com',
            buyerId: 789,
            correlationId: 'corr-123'
        );

        $workflow = new WorkflowContext('instant_call', ['priority' => 'high']);

        $service->startFromClick($click, 'idem-key-1', $workflow);

        expect($logger->records)->toHaveCount(1);
        expect($logger->records[0]['level'])->toBe('info');
        expect($logger->records[0]['message'])->toBe('dlme.checkout.session_started');
        expect($logger->records[0]['context']['idempotency_key'])->toBe('idem-key-1');
        expect($logger->records[0]['context']['seller_id'])->toBe(123);
        expect($logger->records[0]['context']['buyer_id'])->toBe(789);
        expect($logger->records[0]['context']['product_id'])->toBe(456);
        expect($logger->records[0]['context']['sku'])->toBe('SKU-001');
        expect($logger->records[0]['context']['workflow_type'])->toBe('instant_call');
        expect($logger->records[0]['context']['cta_type'])->toBe('instant_checkout');
        expect($logger->records[0]['context']['page_type'])->toBe('product_page');
        expect($logger->records[0]['context']['availability_status'])->toBe('available_now');
        expect($logger->records[0]['context']['referrer_url'])->toBe('https://example.com');
        expect($logger->records[0]['context']['correlation_id'])->toBe('corr-123');
    });

    test('logs session reused with debug level', function () {
        $logger = new ArrayLogger();
        $repository = new InMemoryCheckoutSessionRepository();
        $service = new CheckoutService($repository, null, $logger);

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

        $service->startFromClick($click, 'idem-key-1', $workflow);
        $logger->clear();

        // Try again with same key
        $service->startFromClick($click, 'idem-key-1', $workflow);

        expect($logger->records)->toHaveCount(1);
        expect($logger->records[0]['level'])->toBe('debug');
        expect($logger->records[0]['message'])->toBe('dlme.checkout.session_reused');
    });

    test('logs payment handoff built', function () {
        $logger = new ArrayLogger();
        $repository = new InMemoryCheckoutSessionRepository();
        $service = new CheckoutService($repository, null, $logger);

        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: 456,
            pageType: PageType::PRODUCT_PAGE,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: 'SKU-001',
            price: 99.99,
            referrerUrl: null,
            buyerId: null,
            correlationId: null
        );

        $workflow = new WorkflowContext('instant_call', []);

        $session = $service->startFromClick($click, 'idem-key-1', $workflow);
        $logger->clear();

        $service->buildPaymentHandoffPayload($session);

        expect($logger->records)->toHaveCount(1);
        expect($logger->records[0]['message'])->toBe('dlme.payment.handoff_built');
        expect($logger->records[0]['context']['seller_id'])->toBe(123);
        expect($logger->records[0]['context']['product_id'])->toBe(456);
        expect($logger->records[0]['context']['amount'])->toBe(99.99);
    });

    test('logs payment callback received and session succeeded', function () {
        $logger = new ArrayLogger();
        $repository = new InMemoryCheckoutSessionRepository();
        $service = new CheckoutService($repository, null, $logger);

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

        $service->startFromClick($click, 'idem-key-1', $workflow);
        $logger->clear();

        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: 'txn_123',
            paymentInstrumentRef: 'card_456',
            currency: 'USD',
            amount: 99.99,
            paidAt: new DateTimeImmutable()
        );

        $service->markPaymentSuccessful('idem-key-1', $payment);

        expect($logger->records)->toHaveCount(2);
        expect($logger->records[0]['message'])->toBe('dlme.payment.callback_received');
        expect($logger->records[0]['context']['provider'])->toBe('stripe');
        expect($logger->records[0]['context']['external_transaction'])->toBe('txn_123');
        expect($logger->records[0]['context']['amount'])->toBe(99.99);
        expect($logger->records[0]['context']['currency'])->toBe('USD');

        expect($logger->records[1]['message'])->toBe('dlme.payment.session_succeeded');
        expect($logger->records[1]['context']['seller_id'])->toBe(123);
        expect($logger->records[1]['context']['external_transaction'])->toBe('txn_123');
        expect($logger->records[1]['context']['provider'])->toBe('stripe');
    });

    test('logs session not found error', function () {
        $logger = new ArrayLogger();
        $repository = new InMemoryCheckoutSessionRepository();
        $service = new CheckoutService($repository, null, $logger);

        $payment = new PaymentDetails(
            provider: 'stripe',
            externalTransactionId: 'txn_123',
            paymentInstrumentRef: null,
            currency: 'USD',
            amount: 99.99,
            paidAt: new DateTimeImmutable()
        );

        $service->markPaymentSuccessful('non-existent-key', $payment);

        expect($logger->hasRecord('info', 'dlme.payment.callback_received'))->toBeTrue();
        expect($logger->hasRecord('error', 'dlme.payment.session_not_found'))->toBeTrue();
    });

    test('logs payment failed', function () {
        $logger = new ArrayLogger();
        $repository = new InMemoryCheckoutSessionRepository();
        $service = new CheckoutService($repository, null, $logger);

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

        $service->startFromClick($click, 'idem-key-1', $workflow);
        $logger->clear();

        $service->markPaymentFailed('idem-key-1', 'Card declined');

        expect($logger->records)->toHaveCount(1);
        expect($logger->records[0]['message'])->toBe('dlme.payment.failed');
        expect($logger->records[0]['context']['seller_id'])->toBe(123);
        expect($logger->records[0]['context']['reason'])->toBe('Card declined');
    });

    test('filters null and empty values from context', function () {
        $logger = new ArrayLogger();
        $repository = new InMemoryCheckoutSessionRepository();
        $service = new CheckoutService($repository, null, $logger);

        $click = ButtonClickContext::now(
            sellerId: 123,
            productId: null,  // null
            pageType: PageType::SELLER_LANDING,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            ctaType: CtaType::INSTANT_CHECKOUT,
            sku: null,  // null
            price: null,  // null
            referrerUrl: null,  // null
            buyerId: null,  // null
            correlationId: null  // null
        );

        $workflow = new WorkflowContext('instant_call', []);

        $service->startFromClick($click, 'idem-key-1', $workflow);

        $context = $logger->records[0]['context'];
        expect($context)->not->toHaveKey('product_id');
        expect($context)->not->toHaveKey('buyer_id');
        expect($context)->not->toHaveKey('sku');
        expect($context)->not->toHaveKey('referrer_url');
        expect($context)->not->toHaveKey('correlation_id');
        expect($context)->toHaveKey('seller_id');
        expect($context)->toHaveKey('workflow_type');
    });
});
