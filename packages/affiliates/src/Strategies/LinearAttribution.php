<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Strategies;

use AIArmada\Affiliates\Contracts\AttributionStrategy;
use Illuminate\Support\Collection;

final class LinearAttribution implements AttributionStrategy
{
    public function key(): string
    {
        return 'linear';
    }

    public function label(): string
    {
        return 'Linear';
    }

    public function distribute(Collection $touches): array
    {
        if ($touches->isEmpty()) {
            return [];
        }

        $count = $touches->count();
        $weight = $count > 0 ? (1 / $count) : 0;

        return $touches
            ->groupBy('affiliate_id')
            ->map(fn (Collection $group): float => $weight * $group->count())
            ->toArray();
    }
}
