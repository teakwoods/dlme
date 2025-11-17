<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * In-memory implementation of SellerAvailabilityRepository for testing.
 */
final class InMemorySellerAvailabilityRepository implements SellerAvailabilityRepository
{
    /**
     * @var array<int, bool>
     */
    private array $availableNowFlags = [];

    /**
     * @var array<int, SellerSchedule>
     */
    private array $schedules = [];

    /**
     * @var array<int, string|null>
     */
    private array $timezones = [];

    public function getAvailableNowFlag(int $sellerId): bool
    {
        return $this->availableNowFlags[$sellerId] ?? false;
    }

    public function setAvailableNowFlag(int $sellerId, bool $available): void
    {
        $this->availableNowFlags[$sellerId] = $available;
    }

    public function getSchedule(int $sellerId): SellerSchedule
    {
        if (!isset($this->schedules[$sellerId])) {
            // Return empty schedule if not set
            return SellerSchedule::create([]);
        }

        return $this->schedules[$sellerId];
    }

    public function saveSchedule(int $sellerId, SellerSchedule $schedule): void
    {
        $this->schedules[$sellerId] = $schedule;
    }

    public function getTimezone(int $sellerId): ?string
    {
        return $this->timezones[$sellerId] ?? null;
    }

    public function saveTimezone(int $sellerId, ?string $timezone): void
    {
        if ($timezone === null) {
            unset($this->timezones[$sellerId]);
        } else {
            $this->timezones[$sellerId] = $timezone;
        }
    }
}
