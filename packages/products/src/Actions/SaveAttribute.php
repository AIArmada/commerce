<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Attribute;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class SaveAttribute
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Attribute
    {
        return DB::transaction(function () use ($attributes): Attribute {
            $data = Arr::except($attributes, ['id', 'owner_type', 'owner_id', 'groups']);

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $data['owner_type'] = $owner?->getMorphClass();
                $data['owner_id'] = $owner?->getKey();
            }

            return Attribute::query()->create($data);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Attribute $record, array $attributes): Attribute
    {
        return DB::transaction(function () use ($record, $attributes): Attribute {
            $query = Attribute::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $locked = $query->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $data = Arr::except($attributes, ['id', 'owner_type', 'owner_id', 'groups']);
            $locked->update($data);

            return $locked;
        }, 3);
    }
}
