<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Service for determining what CTA to show based on context.
 */
final class CallToActionService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly CtaStyleConfig $styleConfig,
        private readonly CtaEnabledPolicy $enabledPolicy
    ) {
    }

    /**
     * Decides CTA for a seller's landing page.
     */
    public function decideForSellerLanding(int $sellerId): CtaDecision
    {
        $status = $this->availabilityService->getAvailabilityStatus($sellerId);

        if ($status === AvailabilityStatus::AVAILABLE_NOW) {
            return new CtaDecision(
                type: CtaType::INSTANT_CHECKOUT,
                config: $this->styleConfig->forAvailableNowLanding(),
                availabilityStatus: $status,
                enabled: $this->enabledPolicy->isEnabled(
                    $status,
                    true, // Landing page implies instant consult capability
                    PageType::SELLER_LANDING
                )
            );
        }

        // OFFLINE
        return new CtaDecision(
            type: CtaType::CONTACT_SELLER,
            config: $this->styleConfig->forOfflineLanding(),
            availabilityStatus: $status,
            enabled: $this->enabledPolicy->isEnabled(
                $status,
                true,
                PageType::SELLER_LANDING
            )
        );
    }

    /**
     * Decides CTA for a product page.
     */
    public function decideForProduct(int $sellerId, ProductContext $product): CtaDecision
    {
        $status = $this->availabilityService->getAvailabilityStatus($sellerId);

        // Non-instant consult products are disabled
        if (!$product->isInstantConsult) {
            return new CtaDecision(
                type: CtaType::DISABLED,
                config: $this->styleConfig->forNonInstantProduct(),
                availabilityStatus: $status,
                enabled: false
            );
        }

        // Instant consult product
        if ($status === AvailabilityStatus::AVAILABLE_NOW) {
            return new CtaDecision(
                type: CtaType::INSTANT_CHECKOUT,
                config: $this->styleConfig->forAvailableNowProduct(),
                availabilityStatus: $status,
                enabled: $this->enabledPolicy->isEnabled(
                    $status,
                    $product->isInstantConsult,
                    PageType::PRODUCT_PAGE
                )
            );
        }

        // Instant consult but seller offline
        return new CtaDecision(
            type: CtaType::CONTACT_SELLER,
            config: $this->styleConfig->forOfflineProduct(),
            availabilityStatus: $status,
            enabled: $this->enabledPolicy->isEnabled(
                $status,
                $product->isInstantConsult,
                PageType::PRODUCT_PAGE
            )
        );
    }
}
