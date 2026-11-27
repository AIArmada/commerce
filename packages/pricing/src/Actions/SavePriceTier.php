<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\Pricing\Models\PriceTier;
use Illuminate\Support\Facades\DB;

final readonly class SavePriceTier
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): PriceTier
    {
        return DB::transaction(fn (): PriceTier => PriceTier::query()->create($attributes));
    }

    /** @param array<string, mixed> $attributes */
    public function update(PriceTier $record, array $attributes): PriceTier
    {
        return DB::transaction(function () use ($record, $attributes): PriceTier {
            $locked = PriceTier::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $locked->update($attributes);

            return $locked;
        });
    }

    public function delete(PriceTier $record): bool
    {
        return DB::transaction(function () use ($record): bool {
            $locked = PriceTier::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            return (bool) $locked->delete();
        });
    }
}
