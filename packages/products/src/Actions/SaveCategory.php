<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Category;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class SaveCategory
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Category
    {
        return DB::transaction(function () use ($attributes): Category {
            $data = Arr::except($attributes, ['id', 'owner_type', 'owner_id']);

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $data['owner_type'] = $owner?->getMorphClass();
                $data['owner_id'] = $owner?->getKey();
            }

            return Category::query()->create($data);
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Category $category, array $attributes): Category
    {
        return DB::transaction(function () use ($category, $attributes): Category {
            $query = Category::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            // Serialize concurrent hierarchy moves within the same owner.
            if (array_key_exists('parent_id', $attributes)) {
                (clone $query)->orderBy('id')->lockForUpdate()->pluck('id');
            }

            $locked = $query->whereKey($category->getKey())->lockForUpdate()->firstOrFail();
            $locked->update(Arr::except($attributes, ['id', 'owner_type', 'owner_id']));

            return $locked;
        }, 3);
    }
}
