<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions;

use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class SaveTaxZoneAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes, int | string | null $id = null): TaxZone
    {
        return DB::transaction(function () use ($attributes, $id): TaxZone {
            TaxOwnerScope::resolve([]);

            $record = $id === null
                ? new TaxZone
                : TaxOwnerScope::lockWritable(TaxZone::query(), [$id])->firstOrFail();

            $record->fill($attributes);

            if (! $record->save()) {
                throw new RuntimeException('Tax zone save was cancelled.');
            }

            return $record;
        });
    }
}
