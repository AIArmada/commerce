<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Tax\Models\TaxExemption;
use Illuminate\Support\Facades\DB;

final readonly class RenewTaxExemptionAction
{
    public function execute(int | string $id, string $expiresAt): void
    {
        DB::transaction(function () use ($id, $expiresAt): void {
            $exemption = OwnerWriteGuard::findOrFailForOwner(
                TaxExemption::class,
                $id,
                includeGlobal: false,
                message: 'Tax exemption is not accessible in the current owner scope.',
            );
            $exemption->update(['expires_at' => $expiresAt]);
        });
    }
}
