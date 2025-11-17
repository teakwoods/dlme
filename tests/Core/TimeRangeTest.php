<?php

declare(strict_types=1);

use DLme\Core\TimeRange;
use DLme\Core\InvalidTimeRangeException;

describe('TimeRange', function () {
    test('creates valid time range', function () {
        $range = TimeRange::create(9, 17);

        expect($range->start())->toBe(9);
        expect($range->end())->toBe(17);
    });

    test('rejects start hour below 0', function () {
        TimeRange::create(-1, 17);
    })->throws(InvalidTimeRangeException::class, 'start must be between 0 and 23');

    test('rejects start hour above 23', function () {
        TimeRange::create(24, 17);
    })->throws(InvalidTimeRangeException::class, 'start must be between 0 and 23');

    test('rejects end hour below 1', function () {
        TimeRange::create(9, 0);
    })->throws(InvalidTimeRangeException::class, 'end must be between 1 and 24');

    test('rejects end hour above 24', function () {
        TimeRange::create(9, 25);
    })->throws(InvalidTimeRangeException::class, 'end must be between 1 and 24');

    test('rejects start equal to end', function () {
        TimeRange::create(9, 9);
    })->throws(InvalidTimeRangeException::class, 'start (9) must be less than end (9)');

    test('rejects start greater than end', function () {
        TimeRange::create(17, 9);
    })->throws(InvalidTimeRangeException::class, 'start (17) must be less than end (9)');

    test('contains returns true for hour within range', function () {
        $range = TimeRange::create(9, 17);

        expect($range->contains(9))->toBeTrue();
        expect($range->contains(12))->toBeTrue();
        expect($range->contains(16))->toBeTrue();
    });

    test('contains returns false for hour at end boundary', function () {
        $range = TimeRange::create(9, 17);

        expect($range->contains(17))->toBeFalse();
    });

    test('contains returns false for hour outside range', function () {
        $range = TimeRange::create(9, 17);

        expect($range->contains(8))->toBeFalse();
        expect($range->contains(18))->toBeFalse();
    });

    test('detects overlapping ranges', function () {
        $range1 = TimeRange::create(9, 12);
        $range2 = TimeRange::create(11, 14);

        expect($range1->overlaps($range2))->toBeTrue();
        expect($range2->overlaps($range1))->toBeTrue();
    });

    test('detects non-overlapping ranges', function () {
        $range1 = TimeRange::create(9, 12);
        $range2 = TimeRange::create(13, 17);

        expect($range1->overlaps($range2))->toBeFalse();
        expect($range2->overlaps($range1))->toBeFalse();
    });

    test('detects adjacent ranges as non-overlapping', function () {
        $range1 = TimeRange::create(9, 12);
        $range2 = TimeRange::create(12, 17);

        expect($range1->overlaps($range2))->toBeFalse();
        expect($range2->overlaps($range1))->toBeFalse();
    });

    test('converts to array', function () {
        $range = TimeRange::create(9, 17);

        expect($range->toArray())->toBe([
            'start' => 9,
            'end' => 17,
        ]);
    });
});
