<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Enum representing the status of a checkout session.
 */
enum CheckoutSessionStatus: string
{
    case PENDING   = 'pending';
    case SUCCEEDED = 'succeeded';
    case FAILED    = 'failed';
    case EXPIRED   = 'expired';
}
