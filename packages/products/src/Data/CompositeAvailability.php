<?php

declare(strict_types=1);

namespace AIArmada\Products\Data;

final readonly class CompositeAvailability
{
    /**
     * @param  list<array{
     *     product_id: string,
     *     variant_id: string|null,
     *     sku: string,
     *     quantity: int,
     *     available: int|null,
     *     packages: int|null,
     *     resource_key?: string,
     *     scope_key?: string
     * }>  $components
     */
    public function __construct(
        public ?int $packages,
        public ?string $limitingSku,
        public ?int $limitingAvailableQuantity,
        public array $components,
    ) {}

    /**
     * @return array{
     *     packages: int|null,
     *     limiting_sku: string|null,
     *     limiting_available_quantity: int|null,
     *     components: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'packages' => $this->packages,
            'limiting_sku' => $this->limitingSku,
            'limiting_available_quantity' => $this->limitingAvailableQuantity,
            'components' => $this->components,
        ];
    }
}
