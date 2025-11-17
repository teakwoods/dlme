<?php

declare(strict_types=1);

use DLme\Core\SellerSchedule;
use DLme\Core\TimeRange;
use DLme\Core\DayOfWeek;
use DLme\Core\InvalidScheduleException;

describe('SellerSchedule', function () {
    test('creates empty schedule with all days present', function () {
        $schedule = SellerSchedule::create([]);

        foreach (DayOfWeek::all() as $day) {
            expect($schedule->getRangesForDay($day))->toBe([]);
        }
    });

    test('creates schedule with valid ranges', function () {
        $schedule = SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 12],
                ['start' => 13, 'end' => 17],
            ],
        ]);

        $monRanges = $schedule->getRangesForDay(DayOfWeek::MONDAY);
        expect($monRanges)->toHaveCount(2);
        expect($monRanges[0]->start())->toBe(9);
        expect($monRanges[0]->end())->toBe(12);
        expect($monRanges[1]->start())->toBe(13);
        expect($monRanges[1]->end())->toBe(17);
    });

    test('accepts TimeRange objects directly', function () {
        $range1 = TimeRange::create(9, 12);
        $range2 = TimeRange::create(13, 17);

        $schedule = SellerSchedule::create([
            'mon' => [$range1, $range2],
        ]);

        $monRanges = $schedule->getRangesForDay(DayOfWeek::MONDAY);
        expect($monRanges)->toHaveCount(2);
        expect($monRanges[0])->toBe($range1);
        expect($monRanges[1])->toBe($range2);
    });

    test('sorts ranges by start time', function () {
        $schedule = SellerSchedule::create([
            'mon' => [
                ['start' => 13, 'end' => 17],
                ['start' => 9, 'end' => 12],
            ],
        ]);

        $monRanges = $schedule->getRangesForDay(DayOfWeek::MONDAY);
        expect($monRanges[0]->start())->toBe(9);
        expect($monRanges[1]->start())->toBe(13);
    });

    test('rejects overlapping ranges', function () {
        SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 12],
                ['start' => 11, 'end' => 14],
            ],
        ]);
    })->throws(InvalidScheduleException::class, 'Overlapping time ranges detected for mon');

    test('rejects invalid time range in schedule', function () {
        SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 8], // start > end
            ],
        ]);
    })->throws(InvalidScheduleException::class, 'Invalid time range for mon');

    test('rejects out-of-bounds hours in schedule', function () {
        SellerSchedule::create([
            'mon' => [
                ['start' => -1, 'end' => 12],
            ],
        ]);
    })->throws(InvalidScheduleException::class, 'Invalid time range for mon');

    test('rejects invalid range format', function () {
        SellerSchedule::create([
            'mon' => [
                ['start' => 9], // missing 'end'
            ],
        ]);
    })->throws(InvalidScheduleException::class, 'Invalid time range format for mon');

    test('checks if hour is within schedule', function () {
        $schedule = SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 17],
            ],
        ]);

        expect($schedule->isWithinSchedule(DayOfWeek::MONDAY, 12))->toBeTrue();
        expect($schedule->isWithinSchedule(DayOfWeek::MONDAY, 8))->toBeFalse();
        expect($schedule->isWithinSchedule(DayOfWeek::MONDAY, 18))->toBeFalse();
        expect($schedule->isWithinSchedule(DayOfWeek::TUESDAY, 12))->toBeFalse();
    });

    test('converts to array with all days', function () {
        $schedule = SellerSchedule::create([
            'mon' => [
                ['start' => 9, 'end' => 12],
                ['start' => 13, 'end' => 17],
            ],
        ]);

        $array = $schedule->toArray();

        expect($array)->toHaveKey('mon');
        expect($array)->toHaveKey('tue');
        expect($array)->toHaveKey('wed');
        expect($array)->toHaveKey('thu');
        expect($array)->toHaveKey('fri');
        expect($array)->toHaveKey('sat');
        expect($array)->toHaveKey('sun');

        expect($array['mon'])->toBe([
            ['start' => 9, 'end' => 12],
            ['start' => 13, 'end' => 17],
        ]);

        expect($array['tue'])->toBe([]);
    });
});
