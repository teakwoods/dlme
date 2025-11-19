<?php

declare(strict_types=1);

namespace DLme\Core;

use DateTimeInterface;

/**
 * Represents a checkout session with idempotency support.
 *
 * @property-read string|null $buyerPhone Buyer contact phone number
 * @property-read string|null $buyerEmail Buyer contact email address
 */
final class CheckoutSession
{
    public function __construct(
        public readonly string $id,
        public readonly string $idempotencyKey,
        public readonly int $sellerId,
        public readonly ?int $buyerId,
        public readonly ?int $productId,
        public readonly ?string $sku,
        public readonly ?float $quotedPrice,
        public readonly PageType $pageType,
        public readonly CtaType $ctaType,
        public readonly AvailabilityStatus $availabilityStatus,
        public readonly ?string $referrerUrl,
        public readonly ?string $correlationId,
        public readonly WorkflowContext $workflowContext,
        public readonly DateTimeInterface $createdAt,
        public readonly CheckoutSessionStatus $status,
        public readonly ?string $externalTransactionId = null,
        public readonly ?DateTimeInterface $completedAt = null,
        public readonly ?string $failureReason = null,
        public readonly ?string $buyerPhone = null,
        public readonly ?string $buyerEmail = null,
    ) {
    }
}
