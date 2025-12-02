<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InventoryReleased
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $inventoryable,
        public int $quantity,
        public string $cartId
    ) {}
}
