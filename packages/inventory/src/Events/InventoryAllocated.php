<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Events;

use AIArmada\Inventory\Models\InventoryAllocation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InventoryAllocated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Collection<int, InventoryAllocation>  $allocations
     */
    public function __construct(
        public Model $inventoryable,
        public Collection $allocations,
        public string $cartId
    ) {}

    /**
     * Get total quantity allocated.
     */
    public function getTotalQuantity(): int
    {
        return $this->allocations->sum('quantity');
    }

    /**
     * Get number of locations involved.
     */
    public function getLocationCount(): int
    {
        return $this->allocations->unique('location_id')->count();
    }
}
