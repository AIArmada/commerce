<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions;

use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

            if (array_key_exists('rate', $attributes)) {
                $rate = filter_var($attributes['rate'], FILTER_VALIDATE_INT);

                if ($rate === false || $rate < 0 || $rate > 10000) {
                    throw new InvalidArgumentException('Tax rate must be between 0 and 10000 basis points.');
                }
            }

            $record->save();

            return $record;
        });
    }
}
