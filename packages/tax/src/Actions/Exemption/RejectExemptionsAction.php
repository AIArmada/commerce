<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Illuminate\Support\Facades\DB;

final readonly class RejectExemptionsAction
{
    /**
     * @param  list<int|string>  $ids
     */
    public function execute(array $ids, string $reason): void
    {
        DB::transaction(function () use ($ids, $reason): void {
            foreach (TaxOwnerScope::lockWritable(TaxExemption::query(), $ids) as $exemption) {
                $exemption->reject($reason);
            }
        });
    }
}
