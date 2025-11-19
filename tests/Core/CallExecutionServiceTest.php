<?php

declare(strict_types=1);

namespace Tests\Core;

use DateInterval;
use DateTimeImmutable;
use DLme\Core\ArrayLogger;
use DLme\Core\CallExecutionService;
use DLme\Core\CallRequest;
use DLme\Core\CallRequestStatus;
use DLme\Core\ConsultantPresence;
use DLme\Core\InMemoryCallRequestRepository;
use DLme\Core\InMemoryConsultantPresenceRepository;
use DLme\Core\PricingModel;

beforeEach(function () {
    $this->requests = new InMemoryCallRequestRepository();
    $this->presence = new InMemoryConsultantPresenceRepository();
    $this->service = new CallExecutionService($this->requests, $this->presence, new ArrayLogger());
});

/**
 * Builds a CallRequest with overridable fields to simplify the tests.
 *
 * @param array<string,mixed> $overrides
 */
function buildCallExecutionRequest(array $overrides = []): CallRequest
{
    $now = new DateTimeImmutable();

    return new CallRequest(
        id: $overrides['id'] ?? 'req_test',
        checkoutSessionId: $overrides['checkoutSessionId'] ?? 'sess_123',
        sellerId: $overrides['sellerId'] ?? 456,
        buyerId: $overrides['buyerId'] ?? 789,
        buyerPhone: $overrides['buyerPhone'] ?? '+15551234567',
        buyerEmail: $overrides['buyerEmail'] ?? 'buyer@example.com',
        productId: $overrides['productId'] ?? 321,
        sku: $overrides['sku'] ?? 'PROD-001',
        pricingModel: $overrides['pricingModel'] ?? PricingModel::PAY_AS_YOU_GO,
        agreedPrice: $overrides['agreedPrice'] ?? '100.00',
        currency: $overrides['currency'] ?? 'USD',
        prepaidMinutes: $overrides['prepaidMinutes'] ?? null,
        createdAt: $overrides['createdAt'] ?? $now->sub(new DateInterval('PT5M')),
        initiationWindowStart: $overrides['initiationWindowStart'] ?? $now->sub(new DateInterval('PT1M')),
        initiationWindowEnd: $overrides['initiationWindowEnd'] ?? $now->add(new DateInterval('PT10M')),
        callDurationMinutes: $overrides['callDurationMinutes'] ?? 30,
        scheduledExecutionTime: $overrides['scheduledExecutionTime'] ?? null,
        status: $overrides['status'] ?? CallRequestStatus::PENDING,
        actualCallStartTime: $overrides['actualCallStartTime'] ?? null,
        actualCallEndTime: $overrides['actualCallEndTime'] ?? null,
        actualCallDurationMinutes: $overrides['actualCallDurationMinutes'] ?? null,
        callCompletedSuccessfully: $overrides['callCompletedSuccessfully'] ?? false,
        correlationId: $overrides['correlationId'] ?? 'corr-123',
        referrerUrl: $overrides['referrerUrl'] ?? null,
        supersededByRequestId: $overrides['supersededByRequestId'] ?? null,
        supersededRequestId: $overrides['supersededRequestId'] ?? null
    );
}

describe('CallExecutionService::canExecuteNow - initiation window enforcement', function () {
    test('rejects execution before scheduled window opens', function () {
        $scheduled = (new DateTimeImmutable())->add(new DateInterval('PT10M'));

        $request = buildCallExecutionRequest([
            'id' => 'req_future',
            'status' => CallRequestStatus::SCHEDULED,
            'initiationWindowStart' => $scheduled,
            'initiationWindowEnd' => $scheduled->add(new DateInterval('PT5M')),
            'scheduledExecutionTime' => $scheduled,
        ]);

        $this->requests->save($request);

        $this->presence->updatePresence(
            $request->sellerId,
            new ConsultantPresence('idle', new DateTimeImmutable())
        );

        expect($this->service->canExecuteNow('req_future'))->toBeFalse();
    });

    test('allows execution when within window and consultant idle', function () {
        $request = buildCallExecutionRequest([
            'id' => 'req_ready',
            'status' => CallRequestStatus::SCHEDULED,
            'scheduledExecutionTime' => (new DateTimeImmutable())->sub(new DateInterval('PT1M')),
        ]);

        $this->requests->save($request);

        $this->presence->updatePresence(
            $request->sellerId,
            new ConsultantPresence('idle', new DateTimeImmutable())
        );

        expect($this->service->canExecuteNow('req_ready'))->toBeTrue();
    });
});

describe('CallExecutionService::canExecuteNow - presence enforcement', function () {
    test('rejects execution when consultant is busy', function () {
        $request = buildCallExecutionRequest([
            'id' => 'req_busy',
        ]);

        $this->requests->save($request);

        $this->presence->updatePresence(
            $request->sellerId,
            new ConsultantPresence(
                status: 'in_call',
                timestamp: new DateTimeImmutable(),
                sessionId: 'session123',
                estimatedSessionEnd: (new DateTimeImmutable())->add(new DateInterval('PT15M'))
            )
        );

        expect($this->service->canExecuteNow('req_busy'))->toBeFalse();
    });
});
