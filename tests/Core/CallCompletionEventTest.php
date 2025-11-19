<?php

declare(strict_types=1);

namespace Tests\Core;

use DateTimeImmutable;
use DLme\Core\CallCompletionEvent;

describe('CallCompletionEvent', function () {
    it('creates event with all fields', function () {
        $startedAt = new DateTimeImmutable('2025-01-19 15:00:00');
        $endedAt = new DateTimeImmutable('2025-01-19 15:23:00');

        $event = new CallCompletionEvent(
            callRequestId: 'req_abc123',
            status: 'completed',
            startedAt: $startedAt,
            endedAt: $endedAt,
            durationMinutes: 23,
            consultantAnswered: true,
            clientAnswered: true,
            disconnectReason: 'normal'
        );

        expect($event->callRequestId)->toBe('req_abc123');
        expect($event->status)->toBe('completed');
        expect($event->startedAt)->toBe($startedAt);
        expect($event->endedAt)->toBe($endedAt);
        expect($event->durationMinutes)->toBe(23);
        expect($event->consultantAnswered)->toBeTrue();
        expect($event->clientAnswered)->toBeTrue();
        expect($event->disconnectReason)->toBe('normal');
    });

    it('handles failed call scenario', function () {
        $startedAt = new DateTimeImmutable('2025-01-19 15:00:00');
        $endedAt = new DateTimeImmutable('2025-01-19 15:00:30');

        $event = new CallCompletionEvent(
            callRequestId: 'req_abc123',
            status: 'failed',
            startedAt: $startedAt,
            endedAt: $endedAt,
            durationMinutes: 0,
            consultantAnswered: true,
            clientAnswered: false,
            disconnectReason: 'no_answer'
        );

        expect($event->status)->toBe('failed');
        expect($event->consultantAnswered)->toBeTrue();
        expect($event->clientAnswered)->toBeFalse();
        expect($event->disconnectReason)->toBe('no_answer');
    });

    it('can be created from webhook array', function () {
        $data = [
            'call_request_id' => 'req_abc123',
            'status' => 'completed',
            'started_at' => '2025-01-19T15:00:00+00:00',
            'ended_at' => '2025-01-19T15:23:00+00:00',
            'duration_minutes' => 23,
            'consultant_answered' => true,
            'client_answered' => true,
            'disconnect_reason' => 'normal',
        ];

        $event = CallCompletionEvent::fromArray($data);

        expect($event->callRequestId)->toBe('req_abc123');
        expect($event->status)->toBe('completed');
        expect($event->startedAt->format('c'))->toBe('2025-01-19T15:00:00+00:00');
        expect($event->endedAt->format('c'))->toBe('2025-01-19T15:23:00+00:00');
        expect($event->durationMinutes)->toBe(23);
        expect($event->consultantAnswered)->toBeTrue();
        expect($event->clientAnswered)->toBeTrue();
        expect($event->disconnectReason)->toBe('normal');
    });

    it('can be converted to array', function () {
        $startedAt = new DateTimeImmutable('2025-01-19 15:00:00');
        $endedAt = new DateTimeImmutable('2025-01-19 15:23:00');

        $event = new CallCompletionEvent(
            callRequestId: 'req_abc123',
            status: 'completed',
            startedAt: $startedAt,
            endedAt: $endedAt,
            durationMinutes: 23,
            consultantAnswered: true,
            clientAnswered: true,
            disconnectReason: 'normal'
        );

        $array = $event->toArray();

        expect($array)->toBe([
            'call_request_id' => 'req_abc123',
            'status' => 'completed',
            'started_at' => '2025-01-19T15:00:00+00:00',
            'ended_at' => '2025-01-19T15:23:00+00:00',
            'duration_minutes' => 23,
            'consultant_answered' => true,
            'client_answered' => true,
            'disconnect_reason' => 'normal',
        ]);
    });

    it('is immutable', function () {
        $event = new CallCompletionEvent(
            callRequestId: 'req_abc123',
            status: 'completed',
            startedAt: new DateTimeImmutable(),
            endedAt: new DateTimeImmutable(),
            durationMinutes: 23,
            consultantAnswered: true,
            clientAnswered: true,
            disconnectReason: 'normal'
        );

        expect(fn() => $event->status = 'different')->toThrow(\Error::class);
    });
});
