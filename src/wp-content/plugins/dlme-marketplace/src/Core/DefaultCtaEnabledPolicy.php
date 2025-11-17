<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Default policy: only enable for AVAILABLE_NOW + instant consult.
 */
final class DefaultCtaEnabledPolicy implements CtaEnabledPolicy
{
    public function isEnabled(
        AvailabilityStatus $status,
        bool $isInstantConsult,
        PageType $pageType
    ): bool {
        return $status === AvailabilityStatus::AVAILABLE_NOW && $isInstantConsult;
    }
}
