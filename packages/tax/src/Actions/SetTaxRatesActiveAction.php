<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions;

use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class SetTaxRatesActiveAction
{
    /**
     * @param  list<int|string>  $ids
     */
    public function execute(array $ids, bool $active): void
    {
        DB::transaction(function () use ($ids, $active): void {
            foreach (TaxOwnerScope::lockWritable(TaxRate::query(), $ids) as $rate) {
                if (! $rate->update(['is_active' => $active])) {
                    throw new RuntimeException('Tax rate status update was cancelled.');
                }
            }
        });
    }
}
