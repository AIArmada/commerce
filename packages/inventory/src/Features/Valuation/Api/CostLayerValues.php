<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Valuation\Api;

use AIArmada\Inventory\Enums\CostingMethod;

interface CostLayerValues
{
    public function total(CostingMethod $method, string $currency): int;

    /** @return list<array{inventoryable_type: string, inventoryable_id: string, total_value: int, overall_value: int}> */
    public function rankedItems(CostingMethod $method, string $currency, int $limit): array;

    /**
     * @param list<array{inventoryable_type: string, inventoryable_id: string, location_id: string}> $items
     * @return array<string, array<string, array<string, int>>>
     */
    public function byItemsAtLocations(CostingMethod $method, string $currency, array $items): array;

    /** @return array<string, int> */
    public function byLocation(CostingMethod $method, string $currency): array;
}
