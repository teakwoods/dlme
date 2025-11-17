<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Policy interface for determining if a CTA button should be enabled.
 */
interface CtaEnabledPolicy
{
    /**
     * Determines if a CTA button should be enabled given the context.
     */
    public function isEnabled(
        AvailabilityStatus $status,
        bool $isInstantConsult,
        PageType $pageType
    ): bool;
}
