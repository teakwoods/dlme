<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Enum representing seller availability status.
 */
enum AvailabilityStatus: string
{
    case AVAILABLE_NOW = 'available_now';
    case OFFLINE       = 'offline';
}
