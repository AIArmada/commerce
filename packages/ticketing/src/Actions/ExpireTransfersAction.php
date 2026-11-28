<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Actions;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Ticketing\Models\Pass;
use Carbon\CarbonImmutable;

final readonly class ExpireTransfersAction
{
    public function handle(): int
    {
        $gracePeriod = max(0, (int) config('ticketing.transfers.expiry_grace_period', 0));
        $cutoff = CarbonImmutable::now()->subMinutes($gracePeriod);

        return (int) (new OwnerBatchRunner(Pass::class))
            ->forEachAcrossOwners(fn (): int => Pass::query()
                ->whereNotNull('transfer_expires_at')
                ->where('transfer_expires_at', '<', $cutoff)
                ->update(['transfer_expires_at' => null]))
            ->sum();
    }
}
