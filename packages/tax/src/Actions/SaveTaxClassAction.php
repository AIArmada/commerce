<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions;

use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class SaveTaxClassAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes, int | string | null $id = null): TaxClass
    {
        return DB::transaction(function () use ($attributes, $id): TaxClass {
            TaxOwnerScope::resolve([]);

            $record = $id === null
                ? new TaxClass
                : TaxOwnerScope::lockWritable(TaxClass::query(), [$id])->firstOrFail();

            $record->fill($attributes);

            if (! $record->save()) {
                throw new RuntimeException('Tax class save was cancelled.');
            }

            return $record;
        });
    }
}
