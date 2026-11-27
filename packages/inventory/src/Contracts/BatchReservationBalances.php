<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Contracts;

interface BatchReservationBalances
{
    public function release(string $batchId, string $itemType, string $itemId, string $locationId, int $quantity): void;

    public function commit(string $batchId, string $itemType, string $itemId, string $locationId, int $quantity): void;
}
