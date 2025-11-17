<?php

declare(strict_types=1);

namespace DLme\Core;

use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Core service for managing seller availability.
 *
 * Handles:
 * - Manual "Available now" flag
 * - Weekly schedules with validation
 * - Schedule membership checks
 */
final class AvailabilityService
{
    public function __construct(
        private readonly SellerAvailabilityRepository $repository,
        private readonly TimezoneProvider $timezoneProvider,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Returns true if seller is currently "Available now".
     *
     * v1: only depends on the manual flag.
     */
    public function isSellerAvailableNow(int $sellerId): bool
    {
        return $this->repository->getAvailableNowFlag($sellerId);
    }

    /**
     * Returns the seller's availability status enum.
     */
    public function getAvailabilityStatus(int $sellerId): AvailabilityStatus
    {
        $isAvailable = $this->isSellerAvailableNow($sellerId);

        return $isAvailable
            ? AvailabilityStatus::AVAILABLE_NOW
            : AvailabilityStatus::OFFLINE;
    }

    /**
     * Returns normalized SellerSchedule for the seller.
     */
    public function getSchedule(int $sellerId): SellerSchedule
    {
        return $this->repository->getSchedule($sellerId);
    }

    /**
     * Validates and saves SellerSchedule.
     *
     * @throws InvalidScheduleException on invalid input:
     *   - Out-of-range hours
     *   - start >= end
     *   - Overlapping ranges within a day
     */
    public function saveSchedule(int $sellerId, SellerSchedule $schedule): void
    {
        // Schedule is already validated by SellerSchedule::create()
        // Just persist it
        $this->repository->saveSchedule($sellerId, $schedule);
    }

    /**
     * Sets the manual "Available now" flag.
     */
    public function setAvailableNow(int $sellerId, bool $available): void
    {
        $this->repository->setAvailableNowFlag($sellerId, $available);

        $this->logger->info('dlme.availability.flag_changed', [
            'seller_id'           => $sellerId,
            'availability_status' => $available ? AvailabilityStatus::AVAILABLE_NOW->value : AvailabilityStatus::OFFLINE->value,
            'available'           => $available,
        ]);
    }

    /**
     * Returns true if given local datetime falls within seller's scheduled hours
     * in that seller's effective timezone.
     *
     * Not used for gating in v1, but must be correct and tested.
     */
    public function isWithinScheduledHours(int $sellerId, DateTimeInterface $localDateTime): bool
    {
        $schedule = $this->getSchedule($sellerId);
        $sellerTimezone = $this->timezoneProvider->getSellerTimezone($sellerId);

        // Convert the datetime to seller's timezone
        $dateTimeInSellerTz = \DateTimeImmutable::createFromInterface($localDateTime)
            ->setTimezone($sellerTimezone);

        // Get day of week (mon, tue, etc.)
        $dayOfWeekString = strtolower($dateTimeInSellerTz->format('D'));

        // Map 3-letter abbreviation to our DayOfWeek enum
        $dayOfWeek = match($dayOfWeekString) {
            'mon' => DayOfWeek::MONDAY,
            'tue' => DayOfWeek::TUESDAY,
            'wed' => DayOfWeek::WEDNESDAY,
            'thu' => DayOfWeek::THURSDAY,
            'fri' => DayOfWeek::FRIDAY,
            'sat' => DayOfWeek::SATURDAY,
            'sun' => DayOfWeek::SUNDAY,
            default => throw new \RuntimeException("Invalid day of week: {$dayOfWeekString}"),
        };

        // Get hour (0-23)
        $hour = (int)$dateTimeInSellerTz->format('G');

        return $schedule->isWithinSchedule($dayOfWeek, $hour);
    }
}
