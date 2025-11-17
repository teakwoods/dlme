<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Provider interface for seller timezone resolution.
 *
 * Handles fallback logic when seller has no explicit timezone.
 */
interface TimezoneProvider
{
    /**
     * Returns the effective timezone for a seller.
     *
     * Falls back to a default (e.g., PST) if seller has no timezone set.
     */
    public function getSellerTimezone(int $sellerId): \DateTimeZone;
}
