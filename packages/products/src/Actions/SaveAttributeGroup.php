<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\AttributeGroup;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class SaveAttributeGroup
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): AttributeGroup
    {
        return DB::transaction(function () use ($attributes): AttributeGroup {
            $data = Arr::except($attributes, ['id', 'owner_type', 'owner_id', 'attributes']);

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $data['owner_type'] = $owner?->getMorphClass();
                $data['owner_id'] = $owner?->getKey();
            }

            return AttributeGroup::query()->create($data);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(AttributeGroup $record, array $attributes): AttributeGroup
    {
        return DB::transaction(function () use ($record, $attributes): AttributeGroup {
            $query = AttributeGroup::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $locked = $query->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $data = Arr::except($attributes, ['id', 'owner_type', 'owner_id', 'attributes']);
            $locked->update($data);

            return $locked;
        }, 3);
    }
}
