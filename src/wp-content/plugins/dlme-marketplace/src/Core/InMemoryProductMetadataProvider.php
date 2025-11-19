<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * In-memory implementation of ProductMetadataProvider for testing.
 *
 * Allows tests to configure product metadata without WooCommerce dependency.
 */
final class InMemoryProductMetadataProvider implements ProductMetadataProvider
{
    /**
     * @param array<string, array{
     *     pricing_model: PricingModel,
     *     call_duration_minutes: int,
     *     initiation_window_minutes: int,
     *     prepaid_minutes: int|null
     * }> $products
     */
    public function __construct(
        private array $products = []
    ) {
    }

    /**
     * Configure product metadata for a SKU.
     */
    public function setProduct(
        string $sku,
        PricingModel $pricingModel,
        int $callDurationMinutes,
        int $initiationWindowMinutes,
        ?int $prepaidMinutes = null
    ): void {
        $this->products[$sku] = [
            'pricing_model' => $pricingModel,
            'call_duration_minutes' => $callDurationMinutes,
            'initiation_window_minutes' => $initiationWindowMinutes,
            'prepaid_minutes' => $prepaidMinutes,
        ];
    }

    public function getPricingModel(string $sku): PricingModel
    {
        return $this->products[$sku]['pricing_model'] ?? PricingModel::PAY_AS_YOU_GO;
    }

    public function getCallDurationMinutes(string $sku): int
    {
        return $this->products[$sku]['call_duration_minutes'] ?? 30;
    }

    public function getInitiationWindowMinutes(string $sku): int
    {
        return $this->products[$sku]['initiation_window_minutes'] ?? 15;
    }

    public function getPrepaidMinutes(string $sku): ?int
    {
        return $this->products[$sku]['prepaid_minutes'] ?? null;
    }
}
