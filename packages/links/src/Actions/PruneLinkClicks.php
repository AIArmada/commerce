<?php

declare(strict_types=1);

namespace AIArmada\Links\Actions;

use AIArmada\Links\Models\LinkClick;
use Carbon\CarbonImmutable;

final readonly class PruneLinkClicks
{
    public function handle(CarbonImmutable $cutoff): int
    {
        $pruned = 0;

        do {
            $ids = LinkClick::query()
                ->withoutOwnerScope()
                ->where('occurred_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(1000)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $pruned += LinkClick::query()
                ->withoutOwnerScope()
                ->whereIn('id', $ids)
                ->where('occurred_at', '<', $cutoff)
                ->delete();
        } while ($ids->count() === 1000);

        return $pruned;
    }
}
