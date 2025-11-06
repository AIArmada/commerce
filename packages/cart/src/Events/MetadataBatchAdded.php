<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Cart;

final readonly class MetadataBatchAdded
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $metadata,
        public Cart $cart
    ) {
        //
    }
}
