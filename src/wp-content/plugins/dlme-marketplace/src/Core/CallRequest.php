<?php

declare(strict_types=1);

namespace DLme\Core;

use DateTimeImmutable;

/**
 * Call Request - frozen snapshot of consultation contract.
 *
 * Represents a client's request to connect with a consultant, tying together:
 * - Payment information (from CheckoutSession)
 * - Product/pricing snapshot (from SKU at purchase time)
 * - Timing constraints (initiation window, call duration)
 * - Execution tracking (scheduled time, actual metrics)
 * - Rescheduling relationship (superseding chain)
 *
 * This entity is created after successful payment and tracks the entire
 * lifecycle from creation through execution to completion.
 *
 * State transitions via CallRequestStatus:
 * - PENDING → SCHEDULED (when sent to call system)
 * - PENDING → SUPERSEDED (when consultant reschedules)
 * - SCHEDULED → IN_PROGRESS (call started)
 * - SCHEDULED → EXPIRED (initiation window passed)
 * - IN_PROGRESS → COMPLETED (call ended successfully)
 * - IN_PROGRESS → FAILED (call failed)
 */
final readonly class CallRequest
{
    public function __construct(
        // Identity & References
        public string $id,
        public string $checkoutSessionId,

        // Participants
        public int $sellerId,
        public int $buyerId,
        public string $buyerPhone,
        public ?string $buyerEmail,

        // Product/Pricing (frozen snapshot from SKU)
        public int $productId,
        public string $sku,
        public PricingModel $pricingModel,
        public string $agreedPrice,
        public string $currency,
        public ?int $prepaidMinutes,

        // Timing Constraints (from product metadata)
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $initiationWindowStart,
        public DateTimeImmutable $initiationWindowEnd,
        public int $callDurationMinutes,
        public ?DateTimeImmutable $scheduledExecutionTime,

        // Execution Tracking
        public CallRequestStatus $status,
        public ?DateTimeImmutable $actualCallStartTime,
        public ?DateTimeImmutable $actualCallEndTime,
        public ?int $actualCallDurationMinutes,
        public bool $callCompletedSuccessfully,

        // Metadata
        public string $correlationId,
        public ?string $referrerUrl,

        // Rescheduling Relationship
        public ?string $supersededByRequestId,
        public ?string $supersededRequestId,
    ) {
    }
}
