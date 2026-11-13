<?php

declare(strict_types=1);

namespace AIArmada\Orders\Support;

use JsonException;

/**
 * Immutable, normalized view of one sellable cart line.
 *
 * Composite lines carry a trusted `components` snapshot. The parent line keeps
 * the commercial identity and price; components describe the actual inventory
 * and shipment units for one package.
 */
final readonly class CartLineSnapshot
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{length: int|null, width: int|null, height: int|null}|null  $dimensionsCm
     * @param  list<array{
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
     * }>  $components
     */
    public function __construct(
        public string $lineId,
        public string $name,
        public int $quantity,
        public int $unitPrice,
        public ?string $purchasableId,
        public ?string $purchasableType,
        public ?string $productId,
        public ?string $variantId,
        public ?string $sku,
        public ?string $currency,
        public bool $requiresShipping,
        public bool $isTaxable,
        public ?string $taxClass,
        public ?int $weightGrams,
        public ?array $dimensionsCm,
        public array $components,
        public array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public static function fromArray(array $item): self
    {
        $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
        $associated = is_array($item['associated_model'] ?? null) ? $item['associated_model'] : null;
        $associatedData = is_array($associated['data'] ?? null) ? $associated['data'] : [];

        $productId = self::firstString([
            $item['product_id'] ?? null,
            $attributes['product_id'] ?? null,
            $associatedData['product_id'] ?? null,
        ]);

        $variantId = self::firstString([
            $item['variant_id'] ?? null,
            $attributes['variant_id'] ?? null,
            $associatedData['variant_id'] ?? null,
        ]);

        $purchasableId = self::firstString([
            $item['purchasable_id'] ?? null,
            $attributes['purchasable_id'] ?? null,
            $associated['id'] ?? null,
            $productId,
            $variantId,
            $item['id'] ?? null,
        ]);

        $purchasableType = self::firstString([
            $item['purchasable_type'] ?? null,
            $attributes['purchasable_type'] ?? null,
            $associated['class'] ?? null,
            $associatedData['purchasable_type'] ?? null,
        ]);

        $components = self::normalizeComponents($attributes['components'] ?? null);

        $weight = self::firstInt([
            $item['weight_grams'] ?? null,
            $attributes['weight_grams'] ?? null,
            $item['weight'] ?? null,
            $attributes['weight'] ?? null,
            $associatedData['weight'] ?? null,
        ]);

        $currency = self::firstString([
            $item['currency'] ?? null,
            $attributes['currency'] ?? null,
            $associatedData['currency'] ?? null,
        ]);

        $taxClass = self::firstString([
            $item['tax_class'] ?? null,
            $attributes['tax_class'] ?? null,
            $attributes['tax_category'] ?? null,
            $associatedData['tax_class'] ?? null,
            $associatedData['tax_category'] ?? null,
        ]);

        return new self(
            lineId: self::firstString([$item['id'] ?? null]) ?? '',
            name: self::firstString([$item['name'] ?? null]) ?? '',
            quantity: max(1, self::toInt($item['quantity'] ?? $item['line_quantity'] ?? 1)),
            unitPrice: max(0, self::toInt($item['price'] ?? $item['unit_price'] ?? 0)),
            purchasableId: $purchasableId,
            purchasableType: $purchasableType,
            productId: $productId,
            variantId: $variantId,
            sku: self::firstString([
                $item['sku'] ?? null,
                $attributes['sku'] ?? null,
                $associatedData['sku'] ?? null,
            ]),
            currency: $currency !== null ? mb_strtoupper($currency) : null,
            requiresShipping: self::toBool(
                $item['requires_shipping'] ?? $attributes['requires_shipping'] ?? true,
            ),
            isTaxable: self::toBool(
                $item['is_taxable'] ?? $attributes['is_taxable'] ?? $attributes['taxable'] ?? true,
            ),
            taxClass: $taxClass,
            weightGrams: $weight,
            dimensionsCm: self::firstDimensions([
                $item['dimensions'] ?? null,
                $attributes['dimensions'] ?? null,
                $associatedData['dimensions'] ?? null,
            ], $item, $attributes),
            components: $components,
            attributes: $attributes,
        );
    }

    public function hasComponents(): bool
    {
        return $this->components !== [];
    }

    /**
     * Stable content identity used to detect cart changes without treating a
     * display-name edit as a new composition.
     */
    public function contentFingerprint(): string
    {
        return $this->hash([
            'purchasable_id' => $this->purchasableId,
            'purchasable_type' => $this->purchasableType,
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'components' => $this->components,
        ]);
    }

    public function priceFingerprint(): string
    {
        return $this->hash([
            'purchasable_id' => $this->purchasableId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
        ]);
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public function orderItemMetadata(array $base = []): array
    {
        $metadata = $base;

        if ($this->components !== []) {
            $metadata['components'] = $this->components;
        } elseif ($this->weightGrams !== null) {
            $metadata['weight'] = $this->weightGrams;
        }

        if ($this->components === [] && $this->dimensionsCm !== null) {
            $metadata['dimensions'] = $this->dimensionsCm;
        }

        if ($this->taxClass !== null) {
            $metadata['tax_class'] = $this->taxClass;
        }

        if ($this->hasExplicitTaxability()) {
            $metadata['is_taxable'] = $this->isTaxable;
        }

        return $metadata;
    }

    /**
     * @return list<array{product_id: string, variant_id: string|null, inventoryable_type: string|null, inventoryable_id: string|null, quantity: int, sku: string, name: string, weight: int|null, dimensions: array{length: int, width: int, height: int}|null, requires_shipping: bool, tracks_inventory: bool}>
     */
    public function expandedComponents(): array
    {
        $expanded = [];

        foreach ($this->components as $component) {
            $component['quantity'] *= $this->quantity;
            $expanded[] = $component;
        }

        return $expanded;
    }

    /**
     * @return list<array{product_id: string, variant_id: string|null, inventoryable_type: string|null, inventoryable_id: string|null, quantity: int, sku: string, name: string, weight: int|null, dimensions: array{length: int, width: int, height: int}|null, requires_shipping: bool, tracks_inventory: bool}>
     */
    public static function normalizeComponents(mixed $raw): array
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

            $quantity = self::toInt($component['quantity'] ?? 0);

            if ($quantity < 1) {
                continue;
            }

            $variantId = self::firstString([
                $component['variant_id'] ?? null,
            ]);

            $rawDimensions = is_array($component['dimensions'] ?? null) ? $component['dimensions'] : [];

            $dimensions = self::normalizeDimensions([
                $rawDimensions['length'] ?? null,
                $rawDimensions['width'] ?? null,
                $rawDimensions['height'] ?? null,
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
                'weight' => self::firstInt([
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
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $attributes
     * @return array{length: int, width: int, height: int}|null
     */
    private static function firstDimensions(array $candidates, array $item, array $attributes): ?array
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
            $item['length_cm'] ?? $attributes['length_cm'] ?? null,
            $item['width_cm'] ?? $attributes['width_cm'] ?? null,
            $item['height_cm'] ?? $attributes['height_cm'] ?? null,
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

    private function hasExplicitTaxability(): bool
    {
        return array_key_exists('is_taxable', $this->attributes)
            || array_key_exists('taxable', $this->attributes);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hash(array $payload): string
    {
        try {
            return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return hash('sha256', serialize($payload));
        }
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

    /**
     * @param  array<int, mixed>  $values
     */
    private static function firstInt(array $values): ?int
    {
        foreach ($values as $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $resolved = (int) round((float) $value);

            if ($resolved > 0) {
                return $resolved;
            }
        }

        return null;
    }

    private static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
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
