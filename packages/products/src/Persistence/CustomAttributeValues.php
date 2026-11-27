<?php

declare(strict_types=1);

namespace AIArmada\Products\Persistence;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Support\Facades\DB;

final readonly class CustomAttributeValues
{
    public function set(Product | Variant $record, string $code, mixed $value, ?string $locale = null): AttributeValue
    {
        return DB::transaction(function () use ($record, $code, $value, $locale): AttributeValue {
            $locked = $this->lock($record);
            $attribute = Attribute::query()->forOwner()->where('code', $code)
                ->orderByRaw('CASE WHEN owner_id IS NULL THEN 1 ELSE 0 END')->firstOrFail();

            return $locked->attributeValues()->updateOrCreate(
                ['attribute_id' => $attribute->id, 'locale' => $locale],
                ['value' => $attribute->serializeValue($value)],
            );
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function setMany(Product | Variant $record, array $attributes, ?string $locale = null): void
    {
        DB::transaction(function () use ($record, $attributes, $locale): void {
            foreach ($attributes as $code => $value) {
                $this->set($record, $code, $value, $locale);
            }
        }, 3);
    }

    public function remove(Product | Variant $record, string $code, ?string $locale = null): bool
    {
        return DB::transaction(function () use ($record, $code, $locale): bool {
            return (bool) $this->lock($record)->attributeValues()
                ->whereHas('attribute', fn ($query) => $query->where('code', $code))
                ->where('locale', $locale)->delete();
        }, 3);
    }

    public function clear(Product | Variant $record, ?string $locale = null): int
    {
        return DB::transaction(function () use ($record, $locale): int {
            return $this->lock($record)->attributeValues()
                ->when($locale !== null, fn ($query) => $query->where('locale', $locale))->delete();
        }, 3);
    }

    public function deleteFor(Product | Variant $record): void
    {
        $record->attributeValues()->delete();
    }

    private function lock(Product | Variant $record): Product | Variant
    {
        $query = $record->newQuery();

        if (config('products.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
            $record->scopeForOwner($query, $owner, false);
        }

        return $query->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
    }
}
