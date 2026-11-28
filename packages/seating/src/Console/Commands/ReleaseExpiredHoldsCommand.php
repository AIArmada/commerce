<?php

declare(strict_types=1);

namespace AIArmada\Seating\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Seating\Models\SeatHold;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class ReleaseExpiredHoldsCommand extends Command
{
    protected $signature = 'seating:release-expired-holds {--chunk=500}';

    protected $description = 'Release seat holds whose expires_at is in the past.';

    public function handle(): int
    {
        $chunk = max(1, min(5000, (int) $this->option('chunk')));

        $released = (int) (new OwnerBatchRunner(
            SeatHold::class,
            [
                'enabled' => 'seating.owner.enabled',
                'include_global' => 'seating.owner.include_global',
            ],
        ))->forEachAcrossOwners(fn (): int => $this->releaseForCurrentOwner($chunk))->sum();

        $this->info("Released {$released} expired hold(s).");

        return self::SUCCESS;
    }

    private function releaseForCurrentOwner(int $chunk): int
    {
        $released = 0;

        SeatHold::query()
            ->where('expires_at', '<', CarbonImmutable::now())
            ->whereNull('converted_at')
            ->chunkById($chunk, function (Collection $holds) use (&$released): void {
                $released += $holds->count();
                $holds->each->delete();
            });

        return $released;
    }
}
