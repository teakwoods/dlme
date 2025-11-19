<?php

declare(strict_types=1);

namespace Tests\Core;

use DateTimeImmutable;
use DLme\Core\ArrayLogger;
use DLme\Core\ConsultantPresence;
use DLme\Core\ConsultantPresenceService;
use DLme\Core\InMemoryConsultantPresenceRepository;

beforeEach(function () {
    $this->repository = new InMemoryConsultantPresenceRepository();
    $this->logger = new ArrayLogger();
    $this->service = new ConsultantPresenceService($this->repository, $this->logger);
});

describe('ConsultantPresenceService - updateFromWebhook', function () {
    test('updates presence from webhook', function () {
        $presence = new ConsultantPresence(
            status: 'in_call',
            timestamp: new DateTimeImmutable('2025-01-19 15:30:00'),
            sessionId: 'ext_session_123',
            estimatedSessionEnd: new DateTimeImmutable('2025-01-19 15:45:00'),
            currentCallRequestId: 'req_abc789'
        );

        $this->service->updateFromWebhook(consultantId: 123, presence: $presence);

        $stored = $this->repository->getPresence(123);

        expect($stored)->toBe($presence);
    });

    test('logs presence update', function () {
        $presence = new ConsultantPresence(
            status: 'idle',
            timestamp: new DateTimeImmutable()
        );

        $this->service->updateFromWebhook(123, $presence);

        expect($this->logger->hasRecord('info', 'dlme.presence.updated'))->toBeTrue();
        expect($this->logger->records[0]['context']['consultant_id'])->toBe(123);
        expect($this->logger->records[0]['context']['status'])->toBe('idle');
    });
});

describe('ConsultantPresenceService - getCurrentPresence', function () {
    test('returns current presence', function () {
        $presence = new ConsultantPresence(
            status: 'in_call',
            timestamp: new DateTimeImmutable()
        );

        $this->repository->updatePresence(123, $presence);

        $current = $this->service->getCurrentPresence(123);

        expect($current)->toBe($presence);
    });

    test('returns null when no presence exists', function () {
        $current = $this->service->getCurrentPresence(999);

        expect($current)->toBeNull();
    });
});

describe('ConsultantPresenceService - isAvailableForCall', function () {
    test('returns true when status is idle', function () {
        $presence = new ConsultantPresence(
            status: 'idle',
            timestamp: new DateTimeImmutable()
        );

        $this->repository->updatePresence(123, $presence);

        expect($this->service->isAvailableForCall(123))->toBeTrue();
    });

    test('returns false when status is in_call', function () {
        $presence = new ConsultantPresence(
            status: 'in_call',
            timestamp: new DateTimeImmutable()
        );

        $this->repository->updatePresence(123, $presence);

        expect($this->service->isAvailableForCall(123))->toBeFalse();
    });

    test('returns false when status is offline', function () {
        $presence = new ConsultantPresence(
            status: 'offline',
            timestamp: new DateTimeImmutable()
        );

        $this->repository->updatePresence(123, $presence);

        expect($this->service->isAvailableForCall(123))->toBeFalse();
    });

    test('returns false when no presence exists', function () {
        expect($this->service->isAvailableForCall(999))->toBeFalse();
    });
});
