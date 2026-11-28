<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Illuminate\Support\Facades\DB;

final readonly class DeleteTaxExemptionAction
{
    public function execute(int | string $id): void
    {
        DB::transaction(function () use ($id): void {
            $exemption = TaxOwnerScope::lockWritable(TaxExemption::query(), [$id])->firstOrFail();
            $exemption->delete();
        });
    }
}
