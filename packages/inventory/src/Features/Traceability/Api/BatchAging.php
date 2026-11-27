<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability\Api;

interface BatchAging
{
    /** @return list<array{age_range: string, batch_count: int, total_quantity: int, total_value: int, expiring_soon: int}> */
    public function summarize(): array;
}
