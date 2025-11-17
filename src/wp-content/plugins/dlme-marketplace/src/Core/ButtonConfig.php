<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Configuration for a CTA button's visual appearance.
 */
final class ButtonConfig
{
    public function __construct(
        public readonly string $label,
        public readonly ButtonVisualVariant $variant,
        public readonly string $cssKey
    ) {
    }
}
