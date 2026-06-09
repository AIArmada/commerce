<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Strategies;

use AIArmada\Affiliates\Contracts\AttributionStrategy;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use Illuminate\Support\Collection;

final class FirstTouchAttribution implements AttributionStrategy
{
    public function key(): string
    {
        return 'first_touch';
    }

    public function label(): string
    {
        return 'First Touch';
    }

    public function distribute(Collection $touches): array
    {
        if ($touches->isEmpty()) {
            return [];
        }

        /** @var AffiliateTouchpoint $touch */
        $touch = $touches->sortBy('touched_at')->first();

        return [$touch->affiliate_id => 1.0];
    }
}
