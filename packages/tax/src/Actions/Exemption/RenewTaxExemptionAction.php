<?php

declare(strict_types=1);

namespace AIArmada\Tax\Actions\Exemption;

use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Queries\TaxOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final readonly class RenewTaxExemptionAction
{
    public function execute(int | string $id, string $expiresAt): void
    {
        DB::transaction(function () use ($id, $expiresAt): void {
            $exemption = TaxOwnerScope::lockWritable(TaxExemption::query(), [$id])->firstOrFail();
            if (! $exemption->isApproved()) {
                throw new AuthorizationException('Only approved tax exemptions can be renewed.');
            }

            if (mb_trim($expiresAt) === '') {
                throw new InvalidArgumentException('A new expiry date is required.');
            }

            $expiry = CarbonImmutable::parse($expiresAt);

            if ($exemption->expires_at !== null && $expiry->lessThanOrEqualTo($exemption->expires_at)) {
                throw new InvalidArgumentException('Renewal must extend the existing expiry date.');
            }

            if ($expiry->startOfDay()->lessThanOrEqualTo(CarbonImmutable::today()) || ($exemption->starts_at !== null && $expiry->lessThanOrEqualTo($exemption->starts_at))) {
                throw new InvalidArgumentException('The new expiry must be after today and the exemption start date.');
            }

            if (! $exemption->update(['expires_at' => $expiry])) {
                throw new RuntimeException('Tax exemption renewal was cancelled.');
            }
        });
    }
}
