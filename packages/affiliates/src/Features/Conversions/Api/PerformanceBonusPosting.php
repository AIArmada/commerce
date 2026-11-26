<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface PerformanceBonusPosting
{
    /** @param array<array-key, array<string, mixed>> $bonuses */
    public function award(array $bonuses): int;
}
