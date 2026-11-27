<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Replenishment\Api;

interface ReorderSuggestionCounts
{
    /** @return array{pending: int, approved: int, urgent: int} */
    public function counts(): array;
}
