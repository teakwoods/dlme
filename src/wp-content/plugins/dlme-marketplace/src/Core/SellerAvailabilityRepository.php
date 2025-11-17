<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Repository interface for seller availability data.
 *
 * Provides access to:
 * - Manual "Available now" flag
 * - Weekly schedules
 * - Seller timezones
 */
interface SellerAvailabilityRepository
{
    /**
     * Returns the manual "Available now" flag for a seller.
     */
    public function getAvailableNowFlag(int $sellerId): bool;

    /**
     * Sets the manual "Available now" flag for a seller.
     */
    public function setAvailableNowFlag(int $sellerId, bool $available): void;

    /**
     * Returns a normalized SellerSchedule:
     * - All days 'mon'..'sun' present.
     * - Time ranges non-overlapping and sorted.
     * - Empty arrays if no schedule.
     */
    public function getSchedule(int $sellerId): SellerSchedule;

    /**
     * Persists a normalized SellerSchedule.
     */
    public function saveSchedule(int $sellerId, SellerSchedule $schedule): void;

    /**
     * Returns seller's timezone or null if not set.
     */
    public function getTimezone(int $sellerId): ?string;

    /**
     * Persists seller's timezone or clears it if null.
     */
    public function saveTimezone(int $sellerId, ?string $timezone): void;
}
