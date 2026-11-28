<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Models\PriceTier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class SavePriceTier
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): PriceTier
    {
        return DB::transaction(function () use ($attributes): PriceTier {
            $record = new PriceTier($attributes);

            if (config('pricing.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);

                if ($owner !== null) {
                    $record->assignOwner($owner);
                }
            }

            if (! $record->save()) {
                throw new RuntimeException('Pricing record write was cancelled.');
            }

            return $record;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(PriceTier $record, array $attributes): PriceTier
    {
        return DB::transaction(function () use ($record, $attributes): PriceTier {
            $locked = PriceTier::query()->forOwner()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->update($attributes)) {
                throw new RuntimeException('Pricing record write was cancelled.');
            }

            return $locked;
        });
    }

    public function delete(PriceTier $record): bool
    {
        return DB::transaction(function () use ($record): bool {
            $locked = PriceTier::query()->forOwner()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            return (bool) $locked->delete();
        });
    }
}
