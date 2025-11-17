<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Context about a product for CTA decision making.
 */
final class ProductContext
{
    public function __construct(
        public readonly int $productId,
        public readonly bool $isInstantConsult,
        public readonly ?string $sku = null,
        public readonly ?float $price = null,
    ) {
    }
}
