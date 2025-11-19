<?php

declare(strict_types=1);

namespace Tests\Core;

use DateTimeImmutable;
use DLme\Core\CallExecutionPayload;

describe('CallExecutionPayload', function () {
    it('creates payload with all fields', function () {
        $scheduledTime = new DateTimeImmutable('2025-01-19 16:00:00');

        $payload = new CallExecutionPayload(
            callRequestId: 'req_abc123',
            consultantPhone: '+1234567890',
            clientPhone: '+0987654321',
            durationLimitMinutes: 30,
            pricingModel: 'payg',
            correlationId: 'corr-123',
            scheduledExecutionTime: $scheduledTime,
            metadata: ['priority' => 'high', 'source' => 'web']
        );

        expect($payload->callRequestId)->toBe('req_abc123');
        expect($payload->consultantPhone)->toBe('+1234567890');
        expect($payload->clientPhone)->toBe('+0987654321');
        expect($payload->durationLimitMinutes)->toBe(30);
        expect($payload->pricingModel)->toBe('payg');
        expect($payload->correlationId)->toBe('corr-123');
        expect($payload->scheduledExecutionTime)->toBe($scheduledTime);
        expect($payload->metadata)->toBe(['priority' => 'high', 'source' => 'web']);
    });

    it('allows null optional fields', function () {
        $payload = new CallExecutionPayload(
            callRequestId: 'req_abc123',
            consultantPhone: '+1234567890',
            clientPhone: '+0987654321',
            durationLimitMinutes: 30,
            pricingModel: 'prepaid',
            correlationId: 'corr-123',
            scheduledExecutionTime: null,
            metadata: []
        );

        expect($payload->scheduledExecutionTime)->toBeNull();
        expect($payload->metadata)->toBe([]);
    });

    it('can be converted to array', function () {
        $scheduledTime = new DateTimeImmutable('2025-01-19 16:00:00');

        $payload = new CallExecutionPayload(
            callRequestId: 'req_abc123',
            consultantPhone: '+1234567890',
            clientPhone: '+0987654321',
            durationLimitMinutes: 30,
            pricingModel: 'payg',
            correlationId: 'corr-123',
            scheduledExecutionTime: $scheduledTime,
            metadata: ['priority' => 'high']
        );

        $array = $payload->toArray();

        expect($array)->toHaveKey('call_request_id', 'req_abc123');
        expect($array)->toHaveKey('consultant_phone', '+1234567890');
        expect($array)->toHaveKey('client_phone', '+0987654321');
        expect($array)->toHaveKey('duration_limit_minutes', 30);
        expect($array)->toHaveKey('pricing_model', 'payg');
        expect($array)->toHaveKey('correlation_id', 'corr-123');
        expect($array)->toHaveKey('scheduled_execution_time', '2025-01-19T16:00:00+00:00');
        expect($array)->toHaveKey('metadata', ['priority' => 'high']);
    });

    it('toArray handles null scheduled time', function () {
        $payload = new CallExecutionPayload(
            callRequestId: 'req_abc123',
            consultantPhone: '+1234567890',
            clientPhone: '+0987654321',
            durationLimitMinutes: 30,
            pricingModel: 'prepaid',
            correlationId: 'corr-123',
            scheduledExecutionTime: null,
            metadata: []
        );

        $array = $payload->toArray();

        expect($array['scheduled_execution_time'])->toBeNull();
    });
});
