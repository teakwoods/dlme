<?php

declare(strict_types=1);

namespace Tests\Core;

use DLme\Core\CallRequestStatus;

describe('CallRequestStatus', function () {
    it('has all required status values', function () {
        $statuses = [
            'pending' => CallRequestStatus::PENDING,
            'scheduled' => CallRequestStatus::SCHEDULED,
            'in_progress' => CallRequestStatus::IN_PROGRESS,
            'completed' => CallRequestStatus::COMPLETED,
            'superseded' => CallRequestStatus::SUPERSEDED,
            'expired' => CallRequestStatus::EXPIRED,
            'failed' => CallRequestStatus::FAILED,
        ];

        foreach ($statuses as $expected => $status) {
            expect($status->value)->toBe($expected);
        }
    });

    it('can be created from string value', function () {
        expect(CallRequestStatus::from('pending'))->toBe(CallRequestStatus::PENDING);
        expect(CallRequestStatus::from('scheduled'))->toBe(CallRequestStatus::SCHEDULED);
        expect(CallRequestStatus::from('in_progress'))->toBe(CallRequestStatus::IN_PROGRESS);
        expect(CallRequestStatus::from('completed'))->toBe(CallRequestStatus::COMPLETED);
        expect(CallRequestStatus::from('superseded'))->toBe(CallRequestStatus::SUPERSEDED);
        expect(CallRequestStatus::from('expired'))->toBe(CallRequestStatus::EXPIRED);
        expect(CallRequestStatus::from('failed'))->toBe(CallRequestStatus::FAILED);
    });
});
