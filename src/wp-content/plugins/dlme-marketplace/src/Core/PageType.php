<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Enum representing the page context where a CTA is displayed.
 */
enum PageType: string
{
    case SELLER_LANDING = 'seller_landing';
    case PRODUCT_PAGE   = 'product_page';
}
