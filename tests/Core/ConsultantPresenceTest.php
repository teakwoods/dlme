<?php

declare(strict_types=1);

namespace Tests\Core;

use DateTimeImmutable;
use DLme\Core\ConsultantPresence;

describe('ConsultantPresence', function () {
    it('creates presence with all fields', function () {
        $timestamp = new DateTimeImmutable('2025-01-19 15:30:00');
        $estimatedEnd = new DateTimeImmutable('2025-01-19 15:45:00');

        $presence = new ConsultantPresence(
            status: 'in_call',
            timestamp: $timestamp,
            sessionId: 'ext_session_123',
            estimatedSessionEnd: $estimatedEnd,
            currentCallRequestId: 'req_abc789'
        );

        expect($presence->status)->toBe('in_call');
        expect($presence->timestamp)->toBe($timestamp);
        expect($presence->sessionId)->toBe('ext_session_123');
        expect($presence->estimatedSessionEnd)->toBe($estimatedEnd);
        expect($presence->currentCallRequestId)->toBe('req_abc789');
    });

    it('creates presence with only required fields', function () {
        $timestamp = new DateTimeImmutable('2025-01-19 15:30:00');

        $presence = new ConsultantPresence(
            status: 'idle',
            timestamp: $timestamp
        );

        expect($presence->status)->toBe('idle');
        expect($presence->timestamp)->toBe($timestamp);
        expect($presence->sessionId)->toBeNull();
        expect($presence->estimatedSessionEnd)->toBeNull();
        expect($presence->currentCallRequestId)->toBeNull();
    });

    it('can be converted to array', function () {
        $timestamp = new DateTimeImmutable('2025-01-19 15:30:00');
        $estimatedEnd = new DateTimeImmutable('2025-01-19 15:45:00');

        $presence = new ConsultantPresence(
            status: 'in_call',
            timestamp: $timestamp,
            sessionId: 'ext_session_123',
            estimatedSessionEnd: $estimatedEnd,
            currentCallRequestId: 'req_abc789'
        );

        $array = $presence->toArray();

        expect($array)->toBe([
            'status' => 'in_call',
            'timestamp' => '2025-01-19T15:30:00+00:00',
            'session_id' => 'ext_session_123',
            'estimated_session_end' => '2025-01-19T15:45:00+00:00',
            'current_call_request_id' => 'req_abc789',
        ]);
    });

    it('can be created from array', function () {
        $array = [
            'status' => 'in_call',
            'timestamp' => '2025-01-19T15:30:00+00:00',
            'session_id' => 'ext_session_123',
            'estimated_session_end' => '2025-01-19T15:45:00+00:00',
            'current_call_request_id' => 'req_abc789',
        ];

        $presence = ConsultantPresence::fromArray($array);

        expect($presence->status)->toBe('in_call');
        expect($presence->timestamp->format('c'))->toBe('2025-01-19T15:30:00+00:00');
        expect($presence->sessionId)->toBe('ext_session_123');
        expect($presence->estimatedSessionEnd?->format('c'))->toBe('2025-01-19T15:45:00+00:00');
        expect($presence->currentCallRequestId)->toBe('req_abc789');
    });

    it('fromArray handles null optional fields', function () {
        $array = [
            'status' => 'offline',
            'timestamp' => '2025-01-19T15:30:00+00:00',
        ];

        $presence = ConsultantPresence::fromArray($array);

        expect($presence->status)->toBe('offline');
        expect($presence->sessionId)->toBeNull();
        expect($presence->estimatedSessionEnd)->toBeNull();
        expect($presence->currentCallRequestId)->toBeNull();
    });

    it('is immutable', function () {
        $timestamp = new DateTimeImmutable('2025-01-19 15:30:00');
        $presence = new ConsultantPresence('idle', $timestamp);

        expect($presence)->toBeObject();
        // Attempting to set would fail at runtime with PHP error
        // This test just verifies readonly constructor promotion works
        expect(fn() => $presence->status = 'different')->toThrow(\Error::class);
    });
});
