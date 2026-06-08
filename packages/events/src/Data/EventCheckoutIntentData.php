<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class EventCheckoutIntentData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly Model $buyable,
        public readonly string $cartItemId,
        public readonly int $quantity = 1,
        public readonly array $attributes = [],
        public readonly array $metadata = [],
    ) {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Event checkout intent requires a quantity of at least 1.');
        }

        if (mb_trim($cartItemId) === '') {
            throw new InvalidArgumentException('Event checkout intent requires a cart item identifier.');
        }
    }
}
