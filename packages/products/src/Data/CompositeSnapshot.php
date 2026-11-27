<?php

declare(strict_types=1);

namespace AIArmada\Products\Data;

/**
 * Canonical, immutable composition snapshot for a composite package.
 *
 * Entry keys are shared with cart, checkout, orders, shipping, and inventory:
 * product_id, variant_id, inventoryable_type, inventoryable_id, quantity,
 * sku, name, weight, dimensions, requires_shipping, and tracks_inventory.
 * Nested packages are recursively flattened to leaf entries. The optional
 * composition_path on each nested entry preserves the original ancestry.
 */
final readonly class CompositeSnapshot
{
    public string $fingerprint;

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
     *     tracks_inventory: bool,
     *     unit_price?: int,
     *     metadata?: array<string, mixed>,
     *     composition_path?: list<array{product_id: string, component_id: string, quantity: positive-int, unit_price?: int, metadata?: array<string, mixed>}>
     * }>  $entries
     */
    public function __construct(
        public string $productId,
        public ?string $variantId,
        public string $sku,
        public string $name,
        public int $price,
        public string $currency,
        public string $priceMode,
        public array $entries,
        public int $discountBasisPoints = 0,
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
            'discount_basis_points' => $this->discountBasisPoints,
            'entries' => $this->entries,
        ];
    }
}
