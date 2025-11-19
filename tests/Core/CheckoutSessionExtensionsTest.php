<?php

declare(strict_types=1);

namespace Tests\Core;

use DateTimeImmutable;
use DLme\Core\AvailabilityStatus;
use DLme\Core\CheckoutSession;
use DLme\Core\CheckoutSessionStatus;
use DLme\Core\CtaType;
use DLme\Core\PageType;
use DLme\Core\WorkflowContext;

describe('CheckoutSession - Buyer Contact Extensions', function () {
    it('can be created with buyer contact fields', function () {
        $session = new CheckoutSession(
            id: 'sess_123',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 99.99,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: 'https://example.com',
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::PENDING,
            buyerPhone: '+1234567890',
            buyerEmail: 'buyer@example.com'
        );

        expect($session->buyerPhone)->toBe('+1234567890');
        expect($session->buyerEmail)->toBe('buyer@example.com');
    });

    it('allows null buyer contact fields', function () {
        $session = new CheckoutSession(
            id: 'sess_123',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 99.99,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: 'https://example.com',
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::PENDING,
            buyerPhone: null,
            buyerEmail: null
        );

        expect($session->buyerPhone)->toBeNull();
        expect($session->buyerEmail)->toBeNull();
    });

    it('defaults to null when buyer contact not provided', function () {
        $session = new CheckoutSession(
            id: 'sess_123',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 99.99,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: 'https://example.com',
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::PENDING
        );

        expect($session->buyerPhone)->toBeNull();
        expect($session->buyerEmail)->toBeNull();
    });

    it('buyer contact fields are readonly', function () {
        $session = new CheckoutSession(
            id: 'sess_123',
            idempotencyKey: 'idem-key',
            sellerId: 123,
            buyerId: 456,
            productId: 789,
            sku: 'PROD-001',
            quotedPrice: 99.99,
            pageType: PageType::PRODUCT_PAGE,
            ctaType: CtaType::INSTANT_CHECKOUT,
            availabilityStatus: AvailabilityStatus::AVAILABLE_NOW,
            referrerUrl: 'https://example.com',
            correlationId: 'corr-123',
            workflowContext: new WorkflowContext('instant_call', []),
            createdAt: new DateTimeImmutable(),
            status: CheckoutSessionStatus::PENDING,
            buyerPhone: '+1234567890'
        );

        expect(fn() => $session->buyerPhone = 'different')->toThrow(\Error::class);
    });
});
