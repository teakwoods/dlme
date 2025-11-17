<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Enum representing the type of call-to-action for a seller.
 */
enum CtaType: string
{
    case INSTANT_CHECKOUT = 'instant_checkout';
    case CONTACT_SELLER   = 'contact_seller';
    case MESSAGE_SELLER   = 'message_seller';
    case DISABLED         = 'disabled';
}
