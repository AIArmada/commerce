<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Identity;

use AIArmada\Affiliates\Models\AffiliateClaim;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Revoke a claim. The row is preserved as history: a revoked claim still
 * seals the affiliate and is never re-claimable or reassignable.
 */
final class RevokeAffiliateClaim
{
    use AsAction;

    public function handle(AffiliateClaim $claim): void
    {
        DB::transaction(function () use ($claim): void {
            $locked = AffiliateClaim::query()->lockForUpdate()->findOrFail($claim->getKey());

            if (! $locked->isActive()) {
                return;
            }

            $locked->forceFill(['status' => 'revoked', 'revoked_at' => CarbonImmutable::now()])->save();
        });
    }
}
