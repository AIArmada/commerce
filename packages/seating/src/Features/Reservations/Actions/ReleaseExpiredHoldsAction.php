<?php

declare(strict_types=1);

namespace AIArmada\Seating\Features\Reservations\Actions;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Seating\Features\Reservations\Models\SeatHold;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final readonly class ReleaseExpiredHoldsAction
{
    public function handle(int $chunk = 500): int
    {
        $chunk = max(1, min(5000, $chunk));
        $cutoff = CarbonImmutable::now();

        return (int) (new OwnerBatchRunner(
            SeatHold::class,
            [
                'enabled' => 'seating.owner.enabled',
                'include_global' => 'seating.owner.include_global',
            ],
        ))->forEachAcrossOwners(fn (): int => $this->releaseForCurrentOwner($chunk, $cutoff))->sum();
    }

    private function releaseForCurrentOwner(int $chunk, CarbonImmutable $cutoff): int
    {
        $released = 0;

        SeatHold::query()
            ->where('expires_at', '<', $cutoff)
            ->whereNull('converted_at')
            ->chunkById($chunk, function (Collection $holds) use (&$released, $cutoff): void {
                $released += (new SeatHold)->getConnection()->transaction(function () use ($holds, $cutoff): int {
                    $locked = SeatHold::query()
                        ->whereKey($holds->modelKeys())
                        ->where('expires_at', '<', $cutoff)
                        ->whereNull('converted_at')
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    $count = 0;

                    foreach ($locked as $hold) {
                        if ($hold->delete()) {
                            $count++;
                        }
                    }

                    return $count;
                });
            });

        return $released;
    }
}
