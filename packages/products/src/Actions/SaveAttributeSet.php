<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\AttributeSet;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class SaveAttributeSet
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): AttributeSet
    {
        return DB::transaction(function () use ($attributes): AttributeSet {
            $data = Arr::except($attributes, ['id', 'owner_type', 'owner_id', 'setAttributes', 'groups']);

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $data['owner_type'] = $owner?->getMorphClass();
                $data['owner_id'] = $owner?->getKey();
            }

            return AttributeSet::query()->create($data);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(AttributeSet $record, array $attributes): AttributeSet
    {
        return DB::transaction(function () use ($record, $attributes): AttributeSet {
            $query = AttributeSet::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $locked = $query->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $data = Arr::except($attributes, ['id', 'owner_type', 'owner_id', 'setAttributes', 'groups']);
            $locked->update($data);

            return $locked;
        }, 3);
    }
}
