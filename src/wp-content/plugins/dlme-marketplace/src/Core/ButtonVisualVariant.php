<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Enum representing visual variants for CTA buttons.
 */
enum ButtonVisualVariant: string
{
    case PRIMARY   = 'primary';
    case SECONDARY = 'secondary';
    case GHOST     = 'ghost';
    case DISABLED  = 'disabled';
}
