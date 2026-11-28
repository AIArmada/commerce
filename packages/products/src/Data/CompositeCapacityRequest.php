<?php

declare(strict_types=1);

namespace AIArmada\Products\Data;

final readonly class CompositeCapacityRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $parentProductId,
        public string $componentId,
        public ?string $componentProductId,
        public ?string $variantId,
        public string $inventoryableType,
        public string $inventoryableId,
        public int $quantity,
        public array $metadata,
        public array $context,
        public ?int $stockAvailable,
    ) {}
}
