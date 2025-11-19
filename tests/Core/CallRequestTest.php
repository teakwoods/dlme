<?php

declare(strict_types=1);

namespace Tests\Core;

use DateTimeImmutable;
use DLme\Core\CallRequest;
use DLme\Core\CallRequestStatus;
use DLme\Core\PricingModel;

describe('CallRequest', function () {
    it('creates request with all required fields', function () {
        $createdAt = new DateTimeImmutable('2025-01-19 14:00:00');
        $windowStart = new DateTimeImmutable('2025-01-19 14:00:00');
        $windowEnd = new DateTimeImmutable('2025-01-19 14:15:00');

        $request = new CallRequest(
            id: 'req_abc123',
            checkoutSessionId: 'sess_xyz789',
            sellerId: 123,
            buyerId: 456,
            buyerPhone: '+1234567890',
            buyerEmail: 'buyer@example.com',
            productId: 789,
            sku: 'PROD-001',
            pricingModel: PricingModel::PAY_AS_YOU_GO,
            agreedPrice: '50.00',
            currency: 'USD',
            prepaidMinutes: null,
            createdAt: $createdAt,
            initiationWindowStart: $windowStart,
            initiationWindowEnd: $windowEnd,
            callDurationMinutes: 30,
            scheduledExecutionTime: null,
            status: CallRequestStatus::PENDING,
            actualCallStartTime: null,
            actualCallEndTime: null,
            actualCallDurationMinutes: null,
            callCompletedSuccessfully: false,
            correlationId: 'corr-123',
            referrerUrl: 'https://example.com',
            supersededByRequestId: null,
            supersededRequestId: null
        );

        expect($request->id)->toBe('req_abc123');
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
        expect($request->prepaidMinutes)->toBeNull();
        expect($request->callDurationMinutes)->toBe(30);
        expect($request->status)->toBe(CallRequestStatus::PENDING);
        expect($request->callCompletedSuccessfully)->toBeFalse();
    });

    it('creates prepaid request with prepaid minutes', function () {
        $request = new CallRequest(
            id: 'req_abc123',
            checkoutSessionId: 'sess_xyz789',
            sellerId: 123,
            buyerId: 456,
            buyerPhone: '+1234567890',
            buyerEmail: null,
            productId: 789,
            sku: 'PROD-001',
            pricingModel: PricingModel::PREPAID_TIME,
            agreedPrice: '75.00',
            currency: 'USD',
            prepaidMinutes: 45,
            createdAt: new DateTimeImmutable(),
            initiationWindowStart: new DateTimeImmutable(),
            initiationWindowEnd: new DateTimeImmutable('+15 minutes'),
            callDurationMinutes: 45,
            scheduledExecutionTime: null,
            status: CallRequestStatus::PENDING,
            actualCallStartTime: null,
            actualCallEndTime: null,
            actualCallDurationMinutes: null,
            callCompletedSuccessfully: false,
            correlationId: 'corr-123',
            referrerUrl: null,
            supersededByRequestId: null,
            supersededRequestId: null
        );

        expect($request->pricingModel)->toBe(PricingModel::PREPAID_TIME);
        expect($request->prepaidMinutes)->toBe(45);
        expect($request->buyerEmail)->toBeNull();
        expect($request->referrerUrl)->toBeNull();
    });

    it('creates scheduled request with scheduled execution time', function () {
        $scheduledTime = new DateTimeImmutable('2025-01-19 15:00:00');

        $request = new CallRequest(
            id: 'req_abc123',
            checkoutSessionId: 'sess_xyz789',
            sellerId: 123,
            buyerId: 456,
            buyerPhone: '+1234567890',
            buyerEmail: 'buyer@example.com',
            productId: 789,
            sku: 'PROD-001',
            pricingModel: PricingModel::PAY_AS_YOU_GO,
            agreedPrice: '50.00',
            currency: 'USD',
            prepaidMinutes: null,
            createdAt: new DateTimeImmutable(),
            initiationWindowStart: new DateTimeImmutable(),
            initiationWindowEnd: new DateTimeImmutable('+20 minutes'),
            callDurationMinutes: 30,
            scheduledExecutionTime: $scheduledTime,
            status: CallRequestStatus::SCHEDULED,
            actualCallStartTime: null,
            actualCallEndTime: null,
            actualCallDurationMinutes: null,
            callCompletedSuccessfully: false,
            correlationId: 'corr-123',
            referrerUrl: null,
            supersededByRequestId: null,
            supersededRequestId: null
        );

        expect($request->status)->toBe(CallRequestStatus::SCHEDULED);
        expect($request->scheduledExecutionTime)->toBe($scheduledTime);
    });

    it('creates completed request with actual call metrics', function () {
        $startTime = new DateTimeImmutable('2025-01-19 15:00:00');
        $endTime = new DateTimeImmutable('2025-01-19 15:23:00');

        $request = new CallRequest(
            id: 'req_abc123',
            checkoutSessionId: 'sess_xyz789',
            sellerId: 123,
            buyerId: 456,
            buyerPhone: '+1234567890',
            buyerEmail: 'buyer@example.com',
            productId: 789,
            sku: 'PROD-001',
            pricingModel: PricingModel::PAY_AS_YOU_GO,
            agreedPrice: '50.00',
            currency: 'USD',
            prepaidMinutes: null,
            createdAt: new DateTimeImmutable(),
            initiationWindowStart: new DateTimeImmutable(),
            initiationWindowEnd: new DateTimeImmutable(),
            callDurationMinutes: 30,
            scheduledExecutionTime: null,
            status: CallRequestStatus::COMPLETED,
            actualCallStartTime: $startTime,
            actualCallEndTime: $endTime,
            actualCallDurationMinutes: 23,
            callCompletedSuccessfully: true,
            correlationId: 'corr-123',
            referrerUrl: null,
            supersededByRequestId: null,
            supersededRequestId: null
        );

        expect($request->status)->toBe(CallRequestStatus::COMPLETED);
        expect($request->actualCallStartTime)->toBe($startTime);
        expect($request->actualCallEndTime)->toBe($endTime);
        expect($request->actualCallDurationMinutes)->toBe(23);
        expect($request->callCompletedSuccessfully)->toBeTrue();
    });

    it('tracks superseding relationship', function () {
        $original = new CallRequest(
            id: 'req_original',
            checkoutSessionId: 'sess_xyz789',
            sellerId: 123,
            buyerId: 456,
            buyerPhone: '+1234567890',
            buyerEmail: 'buyer@example.com',
            productId: 789,
            sku: 'PROD-001',
            pricingModel: PricingModel::PAY_AS_YOU_GO,
            agreedPrice: '50.00',
            currency: 'USD',
            prepaidMinutes: null,
            createdAt: new DateTimeImmutable(),
            initiationWindowStart: new DateTimeImmutable(),
            initiationWindowEnd: new DateTimeImmutable(),
            callDurationMinutes: 30,
            scheduledExecutionTime: null,
            status: CallRequestStatus::SUPERSEDED,
            actualCallStartTime: null,
            actualCallEndTime: null,
            actualCallDurationMinutes: null,
            callCompletedSuccessfully: false,
            correlationId: 'corr-123',
            referrerUrl: null,
            supersededByRequestId: 'req_new',
            supersededRequestId: null
        );

        $new = new CallRequest(
            id: 'req_new',
            checkoutSessionId: 'sess_xyz789',
            sellerId: 123,
            buyerId: 456,
            buyerPhone: '+1234567890',
            buyerEmail: 'buyer@example.com',
            productId: 789,
            sku: 'PROD-001',
            pricingModel: PricingModel::PAY_AS_YOU_GO,
            agreedPrice: '50.00',
            currency: 'USD',
            prepaidMinutes: null,
            createdAt: new DateTimeImmutable(),
            initiationWindowStart: new DateTimeImmutable(),
            initiationWindowEnd: new DateTimeImmutable('+10 minutes'),
            callDurationMinutes: 30,
            scheduledExecutionTime: new DateTimeImmutable('+10 minutes'),
            status: CallRequestStatus::SCHEDULED,
            actualCallStartTime: null,
            actualCallEndTime: null,
            actualCallDurationMinutes: null,
            callCompletedSuccessfully: false,
            correlationId: 'corr-123',
            referrerUrl: null,
            supersededByRequestId: null,
            supersededRequestId: 'req_original'
        );

        expect($original->status)->toBe(CallRequestStatus::SUPERSEDED);
        expect($original->supersededByRequestId)->toBe('req_new');
        expect($new->supersededRequestId)->toBe('req_original');
    });

    it('is immutable', function () {
        $request = new CallRequest(
            id: 'req_abc123',
            checkoutSessionId: 'sess_xyz789',
            sellerId: 123,
            buyerId: 456,
            buyerPhone: '+1234567890',
            buyerEmail: 'buyer@example.com',
            productId: 789,
            sku: 'PROD-001',
            pricingModel: PricingModel::PAY_AS_YOU_GO,
            agreedPrice: '50.00',
            currency: 'USD',
            prepaidMinutes: null,
            createdAt: new DateTimeImmutable(),
            initiationWindowStart: new DateTimeImmutable(),
            initiationWindowEnd: new DateTimeImmutable(),
            callDurationMinutes: 30,
            scheduledExecutionTime: null,
            status: CallRequestStatus::PENDING,
            actualCallStartTime: null,
            actualCallEndTime: null,
            actualCallDurationMinutes: null,
            callCompletedSuccessfully: false,
            correlationId: 'corr-123',
            referrerUrl: null,
            supersededByRequestId: null,
            supersededRequestId: null
        );

        expect(fn() => $request->status = CallRequestStatus::COMPLETED)->toThrow(\Error::class);
    });
});
