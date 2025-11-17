<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Payload for handing off to payment provider.
 *
 * Metadata contains all context needed for post-payment reconciliation.
 */
final class PaymentHandoffPayload
{
    /**
     * @param array<string,string> $metadata
     */
    public function __construct(
        public readonly string $checkoutSessionId,
        public readonly string $idempotencyKey,
        public readonly int $sellerId,
        public readonly ?int $buyerId,
        public readonly ?int $productId,
        public readonly ?string $sku,
        public readonly ?float $quotedPrice,
        public readonly array $metadata
    ) {
    }
}
