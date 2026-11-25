<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

use AIArmada\Affiliates\Data\AttributionTouch;

interface AttributionDistribution
{
    /**
     * @param  list<AttributionTouch>  $touches
     * @return array<string, float>
     */
    public function distribute(array $touches, ?string $strategy = null): array;
}
