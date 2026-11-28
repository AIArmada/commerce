<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class SaveCollection
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Collection
    {
        return DB::transaction(function () use ($attributes): Collection {
            $data = Arr::except($attributes, ['id', 'owner_type', 'owner_id', 'products']);

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $data['owner_type'] = $owner?->getMorphClass();
                $data['owner_id'] = $owner?->getKey();
            }

            $this->validateCategoryConditions($data);

            $created = Collection::query()->create($data);

            if (! $created->exists) {
                throw new RuntimeException('Catalog record creation was cancelled.');
            }

            return $created;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Collection $record, array $attributes): Collection
    {
        return DB::transaction(function () use ($record, $attributes): Collection {
            $query = Collection::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $locked = $query->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $data = Arr::except($attributes, ['id', 'owner_type', 'owner_id', 'products']);
            $this->validateCategoryConditions($data);
            if (! $locked->update($data)) {
                throw new RuntimeException('Catalog record update was cancelled.');
            }

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    private function validateCategoryConditions(array $attributes): void
    {
        $conditions = $attributes['conditions'] ?? null;

        if (! is_array($conditions)) {
            return;
        }

        foreach ($conditions as $index => $condition) {
            if (! is_array($condition) || ($condition['field'] ?? null) !== 'category') {
                continue;
            }

            $value = $condition['value'] ?? null;

            if (! is_string($value) || $value === '' || ! Category::query()->forOwner()->whereKey($value)->exists()) {
                throw ValidationException::withMessages([
                    "conditions.{$index}.value" => 'Select a category in the current owner scope.',
                ]);
            }
        }
    }
}
