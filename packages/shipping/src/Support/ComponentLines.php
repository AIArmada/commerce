<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Support;

/**
 * Normalizes the shared cart/order `components` composition snapshot.
 *
 * Shipping reads the frozen snapshot; it never re-resolves catalog components
 * after an order has been placed.
 */
final class ComponentLines
{
    /**
     * @return list<array{
     *     product_id: string,
     *     variant_id: string|null,
     *     inventoryable_type: string|null,
     *     inventoryable_id: string|null,
     *     quantity: int,
     *     sku: string,
     *     name: string,
     *     weight: int|null,
     *     dimensions: array{length: int, width: int, height: int}|null,
     *     requires_shipping: bool,
     *     tracks_inventory: bool
     * }>
     */
    public static function fromMixed(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $components = [];

        foreach (array_values($raw) as $component) {
            if (! is_array($component)) {
                continue;
            }

            $productId = self::firstString([
                $component['product_id'] ?? null,
            ]);

            if ($productId === null) {
                continue;
            }

            $quantity = is_numeric($component['quantity'] ?? null) ? (int) $component['quantity'] : 0;

            if ($quantity < 1) {
                continue;
            }

            $variantId = self::firstString([$component['variant_id'] ?? null]);
            $dimensions = self::dimensions([
                $component['dimensions'] ?? null,
            ]);

            $components[] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'inventoryable_type' => self::firstString([
                    $component['inventoryable_type'] ?? null,
                ]),
                'inventoryable_id' => self::firstString([
                    $component['inventoryable_id'] ?? null,
                    $variantId,
                    $productId,
                ]),
                'quantity' => $quantity,
                'sku' => self::firstString([$component['sku'] ?? null]) ?? '',
                'name' => self::firstString([$component['name'] ?? null]) ?? '',
                'weight' => self::grams([
                    $component['weight'] ?? null,
                ]),
                'dimensions' => $dimensions,
                'requires_shipping' => self::toBool($component['requires_shipping'] ?? true),
                'tracks_inventory' => self::toBool($component['tracks_inventory'] ?? true),
            ];
        }

        return $components;
    }

    /**
     * @param  object  $item  Cart item exposing getAttribute()
     * @return list<array<string, mixed>>
     */
    public static function fromCartItem(object $item): array
    {
        if (! method_exists($item, 'getAttribute')) {
            return [];
        }

        return self::fromMixed($item->getAttribute('components'));
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return list<array<string, mixed>>
     */
    public static function fromOrderItemMetadata(?array $metadata): array
    {
        return self::fromMixed($metadata['components'] ?? null);
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @return list<array<string, mixed>>
     */
    public static function expand(array $components, int $parentQuantity): array
    {
        $expanded = [];

        foreach ($components as $component) {
            $quantity = (int) ($component['quantity'] ?? 0);
            $parentQuantity = max(1, $parentQuantity);
            $component['quantity'] = $quantity * $parentQuantity;
            $expanded[] = $component;
        }

        return $expanded;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    public static function grams(array $values): ?int
    {
        foreach ($values as $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $grams = (int) round((float) $value);

            if ($grams > 0) {
                return $grams;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $candidates
     * @param  array<string, mixed>  $flat
     * @return array{length: int, width: int, height: int}|null
     */
    public static function dimensions(array $candidates, array $flat = []): ?array
    {
        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $dimensions = self::normalizeDimensions([
                $candidate['length'] ?? null,
                $candidate['width'] ?? null,
                $candidate['height'] ?? null,
            ]);

            if ($dimensions !== null) {
                return $dimensions;
            }
        }

        return self::normalizeDimensions([
            $flat['length_cm'] ?? null,
            $flat['width_cm'] ?? null,
            $flat['height_cm'] ?? null,
        ]);
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array{length: int, width: int, height: int}|null
     */
    private static function normalizeDimensions(array $values): ?array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (! is_numeric($value)) {
                return null;
            }

            $normalized[] = (int) round((float) $value);
        }

        if (count($normalized) !== 3 || in_array(0, $normalized, true)) {
            return null;
        }

        /** @var array{length: int, width: int, height: int} $dimensions */
        $dimensions = [
            'length' => $normalized[0],
            'width' => $normalized[1],
            'height' => $normalized[2],
        ];

        return $dimensions;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private static function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $resolved = mb_trim((string) $value);

            if ($resolved !== '') {
                return $resolved;
            }
        }

        return null;
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return ! in_array(mb_strtolower(mb_trim($value)), ['', '0', 'false', 'no', 'off'], true);
        }

        return (bool) $value;
    }
}
