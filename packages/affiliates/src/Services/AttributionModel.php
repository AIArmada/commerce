<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use Illuminate\Support\Collection;

final class AttributionModel
{
    public function distribute(Collection $touches): array
    {
        if ($touches->isEmpty()) {
            return [];
        }

        $model = config('affiliates.tracking.attribution_model', 'last_touch');

        return match ($model) {
            'first_touch' => $this->firstTouch($touches),
            'linear' => $this->linear($touches),
            default => $this->lastTouch($touches),
        };
    }

    private function lastTouch(Collection $touches): array
    {
        /** @var AffiliateTouchpoint $touch */
        $touch = $touches->sortByDesc('touched_at')->first();

        return [$touch->affiliate_id => 1.0];
    }

    private function firstTouch(Collection $touches): array
    {
        /** @var AffiliateTouchpoint $touch */
        $touch = $touches->sortBy('touched_at')->first();

        return [$touch->affiliate_id => 1.0];
    }

    private function linear(Collection $touches): array
    {
        $count = $touches->count();
        $weight = $count > 0 ? (1 / $count) : 0;

        return $touches
            ->groupBy('affiliate_id')
            ->map(fn (Collection $group): float => $weight * $group->count())
            ->toArray();
    }
}
