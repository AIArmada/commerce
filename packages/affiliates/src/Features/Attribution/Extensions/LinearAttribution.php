<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Extensions;

use AIArmada\Affiliates\Contracts\AttributionStrategy;
use AIArmada\Affiliates\Data\AttributionTouch;

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

    /**
     * @param  list<AttributionTouch>  $touches
     * @return array<string, float>
     */
    public function distribute(array $touches): array
    {
        if ($touches === []) {
            return [];
        }

        $counts = [];

        foreach ($touches as $touch) {
            $counts[$touch->affiliateId] = ($counts[$touch->affiliateId] ?? 0) + 1;
        }

        $total = count($touches);

        return array_map(static fn (int $count): float => $count / $total, $counts);
    }
}
