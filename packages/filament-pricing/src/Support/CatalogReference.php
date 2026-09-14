<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Validation\ValidationException;

/**
 * Validate catalog morph references (priceable/tierable pairs) submitted
 * through relation-manager forms: type allowlist plus owner-scoped
 * existence of the referenced row.
 */
final class CatalogReference
{
    /**
     * @return list<class-string>
     */
    public static function allowedTypes(): array
    {
        return [Product::class, Variant::class];
    }

    /**
     * Revalidate submitted morph data, returning it normalized.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validateFormData(array $data, string $typeKey, string $idKey): array
    {
        $reference = self::validate($data, $typeKey, $idKey);

        $data[$typeKey] = $reference['type'];
        $data[$idKey] = $reference['id'];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: class-string, id: string}
     *
     * @throws ValidationException
     */
    public static function validate(array $data, string $typeKey, string $idKey): array
    {
        $type = $data[$typeKey] ?? null;

        if (! is_string($type) || ! in_array($type, self::allowedTypes(), true)) {
            throw ValidationException::withMessages([$typeKey => 'Select a valid catalog item type.']);
        }

        $id = $data[$idKey] ?? null;

        if ((! is_string($id) && ! is_int($id)) || $id === '') {
            throw ValidationException::withMessages([$idKey => 'Select a valid catalog item.']);
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('products.features.owner.include_global', false);

        $exists = $type === Product::class
            ? OwnerQuery::applyToEloquentBuilder(Product::query(), $owner, $includeGlobal)->whereKey($id)->exists()
            : Variant::query()
                ->whereKey($id)
                ->whereHas('product', static function ($query) use ($owner, $includeGlobal): void {
                    OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);
                })
                ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([$idKey => 'The selected catalog item is not accessible in the current owner scope.']);
        }

        return ['type' => $type, 'id' => (string) $id];
    }
}
