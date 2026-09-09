<?php

declare(strict_types=1);

namespace AIArmada\Cart\Support;

final readonly class CartLimits
{
    public function __construct(
        public int $maxItems,
        public int $maxItemQuantity,
        public int $maxDataSizeBytes,
        public int $maxStringLength,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            maxItems: max(0, (int) config('cart.limits.max_items', 1000)),
            maxItemQuantity: max(1, (int) config('cart.limits.max_item_quantity', 10000)),
            maxDataSizeBytes: max(1, (int) config('cart.limits.max_data_size_bytes', 1024 * 1024)),
            maxStringLength: max(1, (int) config('cart.limits.max_string_length', 255)),
        );
    }
}
