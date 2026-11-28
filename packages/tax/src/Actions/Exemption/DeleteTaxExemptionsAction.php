<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class DeleteTaxExemptionsAction
{
    /**
     * @param  list<int|string>  $ids
     */
    public function execute(array $ids): void
    {
        DB::transaction(function () use ($ids): void {
            foreach (TaxOwnerScope::lockWritable(TaxExemption::query(), $ids) as $exemption) {
                if ($exemption->delete() !== true) {
                    throw new RuntimeException('Tax exemption deletion was cancelled.');
                }
            }
        });
    }
}
