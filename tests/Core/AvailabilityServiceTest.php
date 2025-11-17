<?php

declare(strict_types=1);

use DLme\Core\AvailabilityService;
use DLme\Core\AvailabilityStatus;
use DLme\Core\InMemorySellerAvailabilityRepository;
use DLme\Core\InMemoryTimezoneProvider;
use DLme\Core\SellerSchedule;
use DLme\Core\InvalidScheduleException;

beforeEach(function () {
    $this->repository = new InMemorySellerAvailabilityRepository();
    $this->timezoneProvider = new InMemoryTimezoneProvider();
    $this->service = new AvailabilityService($this->repository, $this->timezoneProvider);
});

describe('AvailabilityService - Manual Flag Behavior', function () {
    test('seller is available when flag is true', function () {
        $sellerId = 123;
        $this->repository->setAvailableNowFlag($sellerId, true);

        expect($this->service->isSellerAvailableNow($sellerId))->toBeTrue();
        expect($this->service->getAvailabilityStatus($sellerId))
            ->toBe(AvailabilityStatus::AVAILABLE_NOW);
    });

    test('seller is offline when flag is false', function () {
        $sellerId = 123;
        $this->repository->setAvailableNowFlag($sellerId, false);

        expect($this->service->isSellerAvailableNow($sellerId))->toBeFalse();
        expect($this->service->getAvailabilityStatus($sellerId))
            ->toBe(AvailabilityStatus::OFFLINE);
    });

    test('seller is offline by default when flag not set', function () {
        $sellerId = 123;

        expect($this->service->isSellerAvailableNow($sellerId))->toBeFalse();
        expect($this->service->getAvailabilityStatus($sellerId))
            ->toBe(AvailabilityStatus::OFFLINE);
    });

    test('can set available now flag to true', function () {
        $sellerId = 123;

        $this->service->setAvailableNow($sellerId, true);

        expect($this->service->isSellerAvailableNow($sellerId))->toBeTrue();
    });

    test('can set available now flag to false', function () {
        $sellerId = 123;
        $this->repository->setAvailableNowFlag($sellerId, true);

        $this->service->setAvailableNow($sellerId, false);

        expect($this->service->isSellerAvailableNow($sellerId))->toBeFalse();
    });
});

describe('AvailabilityService - Schedule Validation', function () {
    test('accepts valid schedule', function () {
        $sellerId = 123;
        $schedule = SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 12],
                ['start' => 13, 'end' => 17],
            ],
        ]);

        $this->service->saveSchedule($sellerId, $schedule);

        $retrieved = $this->service->getSchedule($sellerId);
        expect($retrieved->toArray())->toBe($schedule->toArray());
    });

    test('rejects overlapping ranges', function () {
        $sellerId = 123;

        SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 12],
                ['start' => 11, 'end' => 14],
            ],
        ]);
    })->throws(InvalidScheduleException::class, 'Overlapping');

    test('rejects out-of-bounds start hour', function () {
        SellerSchedule::create([
            'mon' => [
                ['start' => -1, 'end' => 12],
            ],
        ]);
    })->throws(InvalidScheduleException::class);

    test('rejects out-of-bounds end hour', function () {
        SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 25],
            ],
        ]);
    })->throws(InvalidScheduleException::class);

    test('rejects start >= end', function () {
        SellerSchedule::create([
            'mon' => [
                ['start' => 12, 'end' => 9],
            ],
        ]);
    })->throws(InvalidScheduleException::class);

    test('normalizes missing days', function () {
        $sellerId = 123;
        $schedule = SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 17],
            ],
        ]);

        $this->service->saveSchedule($sellerId, $schedule);
        $retrieved = $this->service->getSchedule($sellerId);

        $array = $retrieved->toArray();
        expect($array)->toHaveKey('mon');
        expect($array)->toHaveKey('tue');
        expect($array)->toHaveKey('wed');
        expect($array)->toHaveKey('thu');
        expect($array)->toHaveKey('fri');
        expect($array)->toHaveKey('sat');
        expect($array)->toHaveKey('sun');
    });

    test('sorts ranges by start time', function () {
        $sellerId = 123;
        $schedule = SellerSchedule::create([
            'mon' => [
                ['start' => 13, 'end' => 17],
                ['start' => 9, 'end' => 12],
            ],
        ]);

        $this->service->saveSchedule($sellerId, $schedule);
        $retrieved = $this->service->getSchedule($sellerId);

        $monRanges = $retrieved->toArray()['mon'];
        expect($monRanges[0]['start'])->toBe(9);
        expect($monRanges[1]['start'])->toBe(13);
    });
});

describe('AvailabilityService - isWithinScheduledHours', function () {
    test('returns true when inside schedule', function () {
        $sellerId = 123;
        $schedule = SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 17],
            ],
        ]);
        $this->service->saveSchedule($sellerId, $schedule);

        // Set timezone to Europe/Berlin
        $this->timezoneProvider->setSellerTimezone(
            $sellerId,
            new \DateTimeZone('Europe/Berlin')
        );

        // Monday, 2025-01-06 15:30 in Berlin timezone
        $dateTime = new \DateTimeImmutable('2025-01-06 15:30:00', new \DateTimeZone('Europe/Berlin'));

        expect($this->service->isWithinScheduledHours($sellerId, $dateTime))->toBeTrue();
    });

    test('returns false when outside schedule hours', function () {
        $sellerId = 123;
        $schedule = SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 17],
            ],
        ]);
        $this->service->saveSchedule($sellerId, $schedule);

        $this->timezoneProvider->setSellerTimezone(
            $sellerId,
            new \DateTimeZone('Europe/Berlin')
        );

        // Monday, 2025-01-06 20:00 in Berlin timezone (outside 9-17)
        $dateTime = new \DateTimeImmutable('2025-01-06 20:00:00', new \DateTimeZone('Europe/Berlin'));

        expect($this->service->isWithinScheduledHours($sellerId, $dateTime))->toBeFalse();
    });

    test('returns false when outside schedule day', function () {
        $sellerId = 123;
        $schedule = SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 17],
            ],
        ]);
        $this->service->saveSchedule($sellerId, $schedule);

        $this->timezoneProvider->setSellerTimezone(
            $sellerId,
            new \DateTimeZone('Europe/Berlin')
        );

        // Tuesday, 2025-01-07 12:00 in Berlin timezone (mon schedule only)
        $dateTime = new \DateTimeImmutable('2025-01-07 12:00:00', new \DateTimeZone('Europe/Berlin'));

        expect($this->service->isWithinScheduledHours($sellerId, $dateTime))->toBeFalse();
    });

    test('returns false for empty schedule', function () {
        $sellerId = 123;
        $schedule = SellerSchedule::create([]);
        $this->service->saveSchedule($sellerId, $schedule);

        $dateTime = new \DateTimeImmutable('2025-01-06 12:00:00', new \DateTimeZone('UTC'));

        expect($this->service->isWithinScheduledHours($sellerId, $dateTime))->toBeFalse();
    });

    test('handles timezone conversion correctly', function () {
        $sellerId = 123;
        $schedule = SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 17],
            ],
        ]);
        $this->service->saveSchedule($sellerId, $schedule);

        // Seller is in Los Angeles (PST/PDT)
        $this->timezoneProvider->setSellerTimezone(
            $sellerId,
            new \DateTimeZone('America/Los_Angeles')
        );

        // Monday, 2025-01-06 09:00 in LA time (UTC-8)
        // Provided as UTC: 17:00 UTC = 09:00 LA
        $dateTime = new \DateTimeImmutable('2025-01-06 17:00:00', new \DateTimeZone('UTC'));

        expect($this->service->isWithinScheduledHours($sellerId, $dateTime))->toBeTrue();
    });

    test('checks multiple ranges within a day', function () {
        $sellerId = 123;
        $schedule = SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 12],
                ['start' => 13, 'end' => 17],
            ],
        ]);
        $this->service->saveSchedule($sellerId, $schedule);

        $this->timezoneProvider->setSellerTimezone(
            $sellerId,
            new \DateTimeZone('UTC')
        );

        // 11:00 - in first range
        $dt1 = new \DateTimeImmutable('2025-01-06 11:00:00', new \DateTimeZone('UTC'));
        expect($this->service->isWithinScheduledHours($sellerId, $dt1))->toBeTrue();

        // 12:30 - in gap between ranges
        $dt2 = new \DateTimeImmutable('2025-01-06 12:30:00', new \DateTimeZone('UTC'));
        expect($this->service->isWithinScheduledHours($sellerId, $dt2))->toBeFalse();

        // 15:00 - in second range
        $dt3 = new \DateTimeImmutable('2025-01-06 15:00:00', new \DateTimeZone('UTC'));
        expect($this->service->isWithinScheduledHours($sellerId, $dt3))->toBeTrue();
    });

    test('uses default timezone when seller has none set', function () {
        $sellerId = 123;
        $schedule = SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 17],
            ],
        ]);
        $this->service->saveSchedule($sellerId, $schedule);

        // Default is America/Los_Angeles (PST)
        // Monday, 2025-01-06 12:00 PST
        $dateTime = new \DateTimeImmutable('2025-01-06 12:00:00', new \DateTimeZone('America/Los_Angeles'));

        expect($this->service->isWithinScheduledHours($sellerId, $dateTime))->toBeTrue();
    });
});
