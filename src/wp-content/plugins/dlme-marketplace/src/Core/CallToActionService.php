<?php

declare(strict_types=1);

namespace DLme\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service for determining what CTA to show based on context.
 */
final class CallToActionService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly CtaStyleConfig $styleConfig,
        private readonly CtaEnabledPolicy $enabledPolicy,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Decides CTA for a seller's landing page.
     */
    public function decideForSellerLanding(int $sellerId): CtaDecision
    {
        $status = $this->availabilityService->getAvailabilityStatus($sellerId);

        if ($status === AvailabilityStatus::AVAILABLE_NOW) {
            $decision = new CtaDecision(
                type: CtaType::INSTANT_CHECKOUT,
                config: $this->styleConfig->forAvailableNowLanding(),
                availabilityStatus: $status,
                enabled: $this->enabledPolicy->isEnabled(
                    $status,
                    true, // Landing page implies instant consult capability
                    PageType::SELLER_LANDING
                )
            );

            $this->logger->info('dlme.cta.decision', $this->buildContext([
                'seller_id'           => $sellerId,
                'page_type'           => PageType::SELLER_LANDING->value,
                'availability_status' => $status->value,
                'cta_type'            => $decision->type->value,
                'enabled'             => $decision->enabled,
            ]));

            return $decision;
        }

        // OFFLINE
        $decision = new CtaDecision(
            type: CtaType::CONTACT_SELLER,
            config: $this->styleConfig->forOfflineLanding(),
            availabilityStatus: $status,
            enabled: $this->enabledPolicy->isEnabled(
                $status,
                true,
                PageType::SELLER_LANDING
            )
        );

        $this->logger->info('dlme.cta.decision', $this->buildContext([
            'seller_id'           => $sellerId,
            'page_type'           => PageType::SELLER_LANDING->value,
            'availability_status' => $status->value,
            'cta_type'            => $decision->type->value,
            'enabled'             => $decision->enabled,
        ]));

        return $decision;
    }

    /**
     * Decides CTA for a product page.
     */
    public function decideForProduct(int $sellerId, ProductContext $product): CtaDecision
    {
        $status = $this->availabilityService->getAvailabilityStatus($sellerId);

        // Non-instant consult products are disabled
        if (!$product->isInstantConsult) {
            $decision = new CtaDecision(
                type: CtaType::DISABLED,
                config: $this->styleConfig->forNonInstantProduct(),
                availabilityStatus: $status,
                enabled: false
            );

            $this->logger->info('dlme.cta.decision', $this->buildContext([
                'seller_id'           => $sellerId,
                'product_id'          => $product->productId,
                'sku'                 => $product->sku,
                'page_type'           => PageType::PRODUCT_PAGE->value,
                'availability_status' => $status->value,
                'cta_type'            => $decision->type->value,
                'enabled'             => $decision->enabled,
                'is_instant_consult'  => $product->isInstantConsult,
            ]));

            return $decision;
        }

        // Instant consult product
        if ($status === AvailabilityStatus::AVAILABLE_NOW) {
            $decision = new CtaDecision(
                type: CtaType::INSTANT_CHECKOUT,
                config: $this->styleConfig->forAvailableNowProduct(),
                availabilityStatus: $status,
                enabled: $this->enabledPolicy->isEnabled(
                    $status,
                    $product->isInstantConsult,
                    PageType::PRODUCT_PAGE
                )
            );

            $this->logger->info('dlme.cta.decision', $this->buildContext([
                'seller_id'           => $sellerId,
                'product_id'          => $product->productId,
                'sku'                 => $product->sku,
                'page_type'           => PageType::PRODUCT_PAGE->value,
                'availability_status' => $status->value,
                'cta_type'            => $decision->type->value,
                'enabled'             => $decision->enabled,
                'is_instant_consult'  => $product->isInstantConsult,
            ]));

            return $decision;
        }

        // Instant consult but seller offline
        $decision = new CtaDecision(
            type: CtaType::CONTACT_SELLER,
            config: $this->styleConfig->forOfflineProduct(),
            availabilityStatus: $status,
            enabled: $this->enabledPolicy->isEnabled(
                $status,
                $product->isInstantConsult,
                PageType::PRODUCT_PAGE
            )
        );

        $this->logger->info('dlme.cta.decision', $this->buildContext([
            'seller_id'           => $sellerId,
            'product_id'          => $product->productId,
            'sku'                 => $product->sku,
            'page_type'           => PageType::PRODUCT_PAGE->value,
            'availability_status' => $status->value,
            'cta_type'            => $decision->type->value,
            'enabled'             => $decision->enabled,
            'is_instant_consult'  => $product->isInstantConsult,
        ]));

        return $decision;
    }

    /**
     * Builds logging context, filtering out null and empty string values.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildContext(array $context): array
    {
        return array_filter($context, fn($value) => $value !== null && $value !== '');
    }
}
