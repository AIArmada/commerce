<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class RenewTaxExemptionAction
{
    public function execute(int | string $id, string $expiresAt): void
    {
        DB::transaction(function () use ($id, $expiresAt): void {
            $exemption = TaxOwnerScope::lockWritable(TaxExemption::query(), [$id])->firstOrFail();
            $expiry = CarbonImmutable::parse($expiresAt);

            if ($expiry->startOfDay()->lessThanOrEqualTo(CarbonImmutable::today()) || ($exemption->starts_at !== null && $expiry->lessThanOrEqualTo($exemption->starts_at))) {
                throw new InvalidArgumentException('The new expiry must be after today and the exemption start date.');
            }

            $exemption->update(['expires_at' => $expiry]);
        });
    }
}
