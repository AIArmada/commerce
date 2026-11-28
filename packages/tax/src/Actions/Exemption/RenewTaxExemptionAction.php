<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Illuminate\Support\Facades\DB;

final readonly class RenewTaxExemptionAction
{
    public function execute(int | string $id, string $expiresAt): void
    {
        DB::transaction(function () use ($id, $expiresAt): void {
            $exemption = TaxOwnerScope::lockWritable(TaxExemption::query(), [$id])->firstOrFail();
            $exemption->update(['expires_at' => $expiresAt]);
        });
    }
}
