<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Models\Price;
use Illuminate\Support\Facades\DB;

final readonly class SavePrice
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Price
    {
        return DB::transaction(function () use ($attributes): Price {
            $record = new Price($attributes);

            if (config('pricing.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);

                if ($owner !== null) {
                    $record->assignOwner($owner);
                }
            }

            $record->save();

            return $record;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Price $record, array $attributes): Price
    {
        return DB::transaction(function () use ($record, $attributes): Price {
            $locked = Price::query()->forOwner()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $locked->update($attributes);

            return $locked;
        });
    }

    public function delete(Price $record): bool
    {
        return DB::transaction(function () use ($record): bool {
            $locked = Price::query()->forOwner()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            return (bool) $locked->delete();
        });
    }
}
