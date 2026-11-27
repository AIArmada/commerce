<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReplaceOptionValues
{
    /** @param list<array<string, mixed>> $values */
    public function execute(Option $option, array $values): void
    {
        DB::transaction(function () use ($option, $values): void {
            $query = Option::query();
            $products = Product::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
                $products->forOwner($owner, false);
            }

            $parent = $products->whereKey($option->product_id)->lockForUpdate()->firstOrFail();
            $locked = $query->where('product_id', $parent->getKey())->whereKey($option->getKey())->lockForUpdate()->firstOrFail();
            $existing = $locked->values()->reorder()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $retained = [];

            foreach ($values as $index => $value) {
                $id = $value['id'] ?? null;
                $attributes = [
                    'name' => $value['name'],
                    'swatch_color' => $value['swatch_color'] ?? null,
                    'position' => $value['position'] ?? $index,
                ];

                if ($id === null || $id === '') {
                    $created = $locked->values()->create($attributes);
                    $retained[$created->id] = true;

                    continue;
                }

                $record = is_string($id) ? $existing->get($id) : null;

                if (! $record instanceof OptionValue || isset($retained[$id])) {
                    throw ValidationException::withMessages(['option_values' => 'An option value is invalid or duplicated.']);
                }

                $record->update($attributes);
                $retained[$id] = true;
            }

            foreach ($existing as $record) {
                if (! isset($retained[$record->id])) {
                    $record->delete();
                }
            }
        });
    }
}
