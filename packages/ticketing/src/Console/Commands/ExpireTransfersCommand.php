<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Ticketing\Models\Pass;
use Illuminate\Console\Command;

final class ExpireTransfersCommand extends Command
{
    protected $signature = 'ticketing:expire-transfers';

    protected $description = 'Expire transfer windows for passes past their transfer_expires_at';

    public function handle(): int
    {
        $gracePeriod = (int) config('ticketing.transfers.expiry_grace_period', 0);
        $cutoff = now()->subMinutes($gracePeriod);

        $count = OwnerContext::withOwner(null, fn (): int => (int) (new OwnerBatchRunner(Pass::class))
            ->forEach(fn (): int => Pass::query()
                ->whereNotNull('transfer_expires_at')
                ->where('transfer_expires_at', '<', $cutoff)
                ->update(['transfer_expires_at' => null]))
            ->sum());

        $this->info("Expired {$count} transfer windows.");

        return self::SUCCESS;
    }
}
