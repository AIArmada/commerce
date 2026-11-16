<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Support;

use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;

final class CompositeComponentsFormData
{
    /**
     * @return list<array{
     *     component_product_id: string,
     *     component_variant_id: string|null,
     *     quantity: int,
     *     position: int
     * }>
     */
    public static function fromProduct(Product $product): array
    {
        return ProductComponent::query()
            ->where('product_id', $product->getKey())
            ->ordered()
            ->get()
            ->map(static fn (ProductComponent $component): array => [
                'component_product_id' => (string) $component->component_product_id,
                'component_variant_id' => $component->variant_id !== null ? (string) $component->variant_id : null,
                'quantity' => (int) $component->quantity,
                'position' => (int) $component->position,
                'unit_price' => $component->unit_price,
                'metadata_json' => $component->metadata !== null
                    ? json_encode($component->metadata, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
                    : null,
            ])
            ->all();
    }

    /**
     * @param  array<array-key, mixed>  $components
     * @return list<array{
     *     component_product_id: string,
     *     variant_id: string|null,
     *     quantity: int,
     *     position: int
     * }>
     */
    public static function toActionPayload(array $components): array
    {
        $payload = [];

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $componentProductId = mb_trim((string) ($component['component_product_id'] ?? ''));

            if ($componentProductId === '') {
                continue;
            }

            $variantId = mb_trim((string) ($component['component_variant_id'] ?? ''));

            $payload[] = [
                'component_product_id' => $componentProductId,
                'variant_id' => $variantId !== '' ? $variantId : null,
                'quantity' => max(1, (int) ($component['quantity'] ?? 1)),
                'position' => (int) ($component['position'] ?? count($payload)),
                'unit_price' => $component['unit_price'] ?? null,
                'metadata' => isset($component['metadata_json']) && trim((string) $component['metadata_json']) !== ''
                    ? json_decode((string) $component['metadata_json'], true, 512, JSON_THROW_ON_ERROR)
                    : null,
            ];
        }

        return $payload;
    }
}
