<?php

declare(strict_types=1);

namespace AIArmada\Products\Queries;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use BackedEnum;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

final readonly class CatalogExport
{
    public const array FIELDS = [
        'name', 'sku', 'slug', 'description', 'short_description', 'currency',
        'price', 'compare_price', 'cost', 'weight', 'status', 'type',
        'visibility', 'is_featured', 'is_taxable', 'requires_shipping', 'tax_class',
    ];

    /**
     * @param  array<int, mixed>  $fields
     * @return LazyCollection<int, non-empty-list<string|int|float|null>>
     */
    public function rows(array $fields, string $status = 'all'): LazyCollection
    {
        if ($fields === [] || array_any($fields, static fn (mixed $field): bool => ! is_string($field) || ! in_array($field, self::FIELDS, true))) {
            throw ValidationException::withMessages(['fields' => 'Select only supported export fields.']);
        }

        if ($status !== 'all' && ProductStatus::tryFrom($status) === null) {
            throw ValidationException::withMessages(['status_filter' => 'Invalid product status.']);
        }

        $owner = OwnerContext::resolve();

        if (config('products.features.owner.enabled', false)) {
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
        }

        $query = Product::query()->forOwner($owner, (bool) config('products.features.owner.include_global', false))
            ->select(array_values(array_unique(['id', ...$fields])));

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query->lazyById(500)->map(static function (Product $product) use ($fields): array {
            $row = [];

            foreach ($fields as $field) {
                $value = $product->getAttribute($field);

                if (in_array($field, ['price', 'compare_price', 'cost'], true) && is_numeric($value)) {
                    $value = $value / 100;
                }

                if ($value instanceof BackedEnum) {
                    $value = $value->value;
                }

                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }

                if (is_string($value) && preg_match('/^(?:[\t\r\n]|[\x00-\x20]*[=+@-])/u', $value) === 1) {
                    $value = "'" . $value;
                }

                $row[] = is_string($value) || is_int($value) || is_float($value) ? $value : null;
            }

            return $row;
        });
    }
}
