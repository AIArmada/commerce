<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

/**
 * Canonical, immutable composition snapshot for a composite package.
 *
 * Entry keys are shared with cart, checkout, orders, shipping, and inventory:
 * product_id, variant_id, inventoryable_type, inventoryable_id, quantity,
 * sku, name, weight, dimensions, requires_shipping, and tracks_inventory.
 */
final class CompositeSnapshot
{
    public readonly string $fingerprint;

    /**
     * @param  list<array{
     *     product_id: string,
     *     variant_id: string|null,
     *     inventoryable_type: string,
     *     inventoryable_id: string,
     *     quantity: int,
     *     sku: string,
     *     name: string,
     *     weight: int|null,
     *     dimensions: array{length: int, width: int, height: int}|null,
     *     requires_shipping: bool,
     *     tracks_inventory: bool
     * }>  $entries
     */
    public function __construct(
        public readonly string $productId,
        public readonly ?string $variantId,
        public readonly string $sku,
        public readonly string $name,
        public readonly int $price,
        public readonly string $currency,
        public readonly string $priceMode,
        public readonly array $entries,
    ) {
        $this->fingerprint = hash('sha256', json_encode($this->canonicalPayload(), JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{
     *     product_id: string,
     *     variant_id: string|null,
     *     sku: string,
     *     name: string,
     *     price: int,
     *     currency: string,
     *     price_mode: string,
     *     entries: list<array<string, mixed>>,
     *     fingerprint: string
     * }
     */
    public function toArray(): array
    {
        return [
            ...$this->canonicalPayload(),
            'fingerprint' => $this->fingerprint,
        ];
    }

    public function matches(self $other): bool
    {
        return $this->fingerprint === $other->fingerprint;
    }

    /**
     * @return array{
     *     product_id: string,
     *     variant_id: string|null,
     *     sku: string,
     *     name: string,
     *     price: int,
     *     currency: string,
     *     price_mode: string,
     *     entries: list<array<string, mixed>>
     * }
     */
    private function canonicalPayload(): array
    {
        return [
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'sku' => $this->sku,
            'name' => $this->name,
            'price' => $this->price,
            'currency' => $this->currency,
            'price_mode' => $this->priceMode,
            'entries' => $this->entries,
        ];
    }
}
