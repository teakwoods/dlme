<?php

declare(strict_types=1);

namespace DLme\Core;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Context captured when a CTA button is clicked.
 */
final class ButtonClickContext
{
    public function __construct(
        public readonly int $sellerId,
        public readonly ?int $productId,
        public readonly PageType $pageType,
        public readonly AvailabilityStatus $availabilityStatus,
        public readonly CtaType $ctaType,
        public readonly ?string $sku,
        public readonly ?float $price,
        public readonly ?string $referrerUrl,
        public readonly ?int $buyerId,
        public readonly ?string $correlationId,
        public readonly DateTimeInterface $clickedAt,
    ) {
    }

    /**
     * Factory method to create a ButtonClickContext with current timestamp.
     */
    public static function now(
        int $sellerId,
        ?int $productId,
        PageType $pageType,
        AvailabilityStatus $availabilityStatus,
        CtaType $ctaType,
        ?string $sku,
        ?float $price,
        ?string $referrerUrl,
        ?int $buyerId,
        ?string $correlationId
    ): self {
        return new self(
            $sellerId,
            $productId,
            $pageType,
            $availabilityStatus,
            $ctaType,
            $sku,
            $price,
            $referrerUrl,
            $buyerId,
            $correlationId,
            new DateTimeImmutable()
        );
    }
}
