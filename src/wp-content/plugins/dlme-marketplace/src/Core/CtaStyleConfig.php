<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Configuration for CTA button styles.
 *
 * Provides Apple Store-style small, classy button configurations.
 */
final class CtaStyleConfig
{
    /**
     * Seller landing page, seller available now.
     */
    public function forAvailableNowLanding(): ButtonConfig
    {
        return new ButtonConfig(
            label: 'Talk to me now',
            variant: ButtonVisualVariant::PRIMARY,
            cssKey: 'btn-instant-landing'
        );
    }

    /**
     * Seller landing page, seller offline.
     */
    public function forOfflineLanding(): ButtonConfig
    {
        return new ButtonConfig(
            label: 'Contact me',
            variant: ButtonVisualVariant::SECONDARY,
            cssKey: 'btn-contact-landing'
        );
    }

    /**
     * Product page, instant consult, seller available now.
     */
    public function forAvailableNowProduct(): ButtonConfig
    {
        return new ButtonConfig(
            label: 'Buy Now',
            variant: ButtonVisualVariant::PRIMARY,
            cssKey: 'btn-instant-product'
        );
    }

    /**
     * Product page, instant consult, seller offline.
     */
    public function forOfflineProduct(): ButtonConfig
    {
        return new ButtonConfig(
            label: 'Notify Me',
            variant: ButtonVisualVariant::SECONDARY,
            cssKey: 'btn-notify-product'
        );
    }

    /**
     * Product page, non-instant consult product.
     */
    public function forNonInstantProduct(): ButtonConfig
    {
        return new ButtonConfig(
            label: 'Learn More',
            variant: ButtonVisualVariant::DISABLED,
            cssKey: 'btn-disabled-product'
        );
    }
}
