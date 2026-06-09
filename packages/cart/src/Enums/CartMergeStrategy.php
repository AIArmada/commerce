<?php

declare(strict_types=1);

namespace AIArmada\Cart\Enums;

enum CartMergeStrategy: string
{
    case ADD_QUANTITIES = 'add_quantities';
    case KEEP_HIGHEST_QUANTITY = 'keep_highest_quantity';
    case KEEP_USER_CART = 'keep_user_cart';
    case REPLACE_WITH_GUEST = 'replace_with_guest';
}
