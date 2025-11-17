<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * In-memory implementation of TimezoneProvider for testing.
 *
 * Allows configuring per-seller timezones or using a fixed default.
 */
final class InMemoryTimezoneProvider implements TimezoneProvider
{
    private const DEFAULT_TIMEZONE = 'America/Los_Angeles'; // PST

    /**
     * @var array<int, \DateTimeZone>
     */
    private array $sellerTimezones = [];

    private \DateTimeZone $defaultTimezone;

    public function __construct(?string $defaultTimezone = null)
    {
        $this->defaultTimezone = new \DateTimeZone(
            $defaultTimezone ?? self::DEFAULT_TIMEZONE
        );
    }

    /**
     * Configure a specific timezone for a seller (for testing).
     */
    public function setSellerTimezone(int $sellerId, \DateTimeZone $timezone): void
    {
        $this->sellerTimezones[$sellerId] = $timezone;
    }

    public function getSellerTimezone(int $sellerId): \DateTimeZone
    {
        return $this->sellerTimezones[$sellerId] ?? $this->defaultTimezone;
    }
}
