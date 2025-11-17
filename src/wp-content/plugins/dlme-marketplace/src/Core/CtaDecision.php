<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Represents the decision about what CTA to show and how.
 */
final class CtaDecision
{
    public function __construct(
        public readonly CtaType $type,
        public readonly ButtonConfig $config,
        public readonly AvailabilityStatus $availabilityStatus,
        public readonly bool $enabled,
    ) {
    }
}
