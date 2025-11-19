<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Pricing model for consultation products.
 *
 * PAY_AS_YOU_GO (PAYG):
 * - Consultant lists service with per-minute rate
 * - Client pre-authorizes amount (e.g., $50)
 * - Actual call duration tracked
 * - Actual charge = duration * rate (up to pre-auth limit)
 *
 * PREPAID_TIME:
 * - Consultant lists service with fixed time package (e.g., "30 min for $50")
 * - Client pays fixed amount upfront
 * - Call disconnects automatically at duration limit
 * - No variable billing
 */
enum PricingModel: string
{
    case PAY_AS_YOU_GO = 'payg';
    case PREPAID_TIME = 'prepaid';
}
