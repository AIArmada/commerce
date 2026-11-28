<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Contracts;

interface InventoryableInterface
{
    public function getTotalOnHand(): int;

    public function getTotalAvailable(): int;

    public function hasInventory(int $quantity): bool;
}
