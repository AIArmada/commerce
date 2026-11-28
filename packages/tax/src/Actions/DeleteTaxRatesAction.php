<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions;

use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Illuminate\Support\Facades\DB;

final readonly class DeleteTaxRatesAction
{
    /**
     * @param  list<int|string>  $ids
     */
    public function execute(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            foreach (TaxOwnerScope::lockWritable(TaxRate::query(), $ids) as $record) {
                $record->delete();
            }
        });
    }
}
