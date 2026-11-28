<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions;

use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class SaveTaxRateAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes, int | string | null $id = null): TaxRate
    {
        return DB::transaction(function () use ($attributes, $id): TaxRate {
            TaxOwnerScope::resolve([]);

            $record = $id === null
                ? new TaxRate
                : TaxOwnerScope::lockWritable(TaxRate::query(), [$id])->firstOrFail();

            $record->fill($attributes);

            if (! $record->save()) {
                throw new RuntimeException('Tax rate save was cancelled.');
            }

            return $record;
        });
    }
}
