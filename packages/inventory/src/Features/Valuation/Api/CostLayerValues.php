<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Api;

use AIArmada\Inventory\Enums\CostingMethod;

interface CostLayerValues
{
    public function total(CostingMethod $method, string $currency): int;

    /** @return array<string, int> */
    public function byLocation(CostingMethod $method, string $currency): array;
}
