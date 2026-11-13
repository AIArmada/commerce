<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

final class CompositeAvailability
{
    /**
     * @param  list<array{
     *     product_id: string,
     *     variant_id: string|null,
     *     sku: string,
     *     quantity: int,
     *     available: int,
     *     packages: int
     * }>  $components
     */
    public function __construct(
        public readonly int $packages,
        public readonly ?string $limitingSku,
        public readonly ?int $limitingAvailableQuantity,
        public readonly array $components,
    ) {}

    /**
     * @return array{
     *     packages: int,
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
