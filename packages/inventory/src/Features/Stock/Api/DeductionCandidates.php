<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

interface DeductionCandidates
{
    /**
     * @param  list<array{inventoryable_type: string, inventoryable_id: string}>  $items
     * @return array<string, list<array{location_id: string, available: int}>>
     */
    public function locked(array $items): array;
}
