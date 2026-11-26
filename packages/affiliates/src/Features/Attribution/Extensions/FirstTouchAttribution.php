<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Extensions;

use AIArmada\Affiliates\Contracts\AttributionStrategy;
use AIArmada\Affiliates\Data\AttributionTouch;

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

    /**
     * @param  list<AttributionTouch>  $touches
     * @return array<string, float>
     */
    public function distribute(array $touches): array
    {
        $selected = null;

        foreach ($touches as $touch) {
            if ($selected === null || $touch->touchedAt < $selected->touchedAt) {
                $selected = $touch;
            }
        }

        return $selected === null ? [] : [$selected->affiliateId => 1.0];
    }
}
