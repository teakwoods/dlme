<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Interface for retrieving product configuration metadata.
 *
 * Abstracts access to product/SKU configuration from WooCommerce/Dokan.
 * Allows core domain logic to remain framework-agnostic.
 *
 * Production implementation (by full-scope agent):
 * - WooCommerceProductMetadataProvider reads from product custom fields
 * - Consultants configure via Dokan product editor
 *
 * Test implementation (SANDBOX):
 * - InMemoryProductMetadataProvider with hardcoded/configured values
 */
interface ProductMetadataProvider
{
    /**
     * Get pricing model for a SKU.
     *
     * @param string $sku Product SKU
     * @return PricingModel PAYG or PREPAID_TIME
     */
    public function getPricingModel(string $sku): PricingModel;

    /**
     * Get call duration in minutes for a SKU.
     *
     * For PAYG: Maximum allowed duration before disconnect
     * For PREPAID: Fixed duration included in price
     *
     * @param string $sku Product SKU
     * @return int Duration in minutes
     */
    public function getCallDurationMinutes(string $sku): int;

    /**
     * Get initiation window in minutes for a SKU.
     *
     * How long from purchase the call must be initiated.
     * Example: 15 means call must start within 15 minutes of purchase.
     *
     * @param string $sku Product SKU
     * @return int Window in minutes
     */
    public function getInitiationWindowMinutes(string $sku): int;

    /**
     * Get prepaid minutes for a SKU (PREPAID_TIME model only).
     *
     * Returns null for PAYG products.
     * For PREPAID products, typically same as getCallDurationMinutes().
     *
     * @param string $sku Product SKU
     * @return int|null Prepaid minutes, or null for PAYG
     */
    public function getPrepaidMinutes(string $sku): ?int;
}
