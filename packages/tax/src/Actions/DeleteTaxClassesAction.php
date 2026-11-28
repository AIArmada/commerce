<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions;

use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class DeleteTaxClassesAction
{
    /**
     * @param  list<int|string>  $ids
     */
    public function execute(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            foreach (TaxOwnerScope::lockWritable(TaxClass::query(), $ids) as $record) {
                if ($record->delete() !== true) {
                    throw new RuntimeException('Tax class deletion was cancelled.');
                }
            }
        });
    }
}
