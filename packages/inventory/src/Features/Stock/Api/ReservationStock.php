<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

interface ReservationStock
{
    public function reserve(string $levelId, int $quantity): void;

    public function release(string $levelId, int $quantity): void;

    public function commit(string $levelId, int $quantity, ?string $orderId, string $note, ?string $batchId = null): CommittedStockMovement;
}
