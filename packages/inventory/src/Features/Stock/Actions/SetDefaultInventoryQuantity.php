<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class SetDefaultInventoryQuantity
{
    public function __construct(private InventoryService $inventory) {}

    public function execute(Model $item, int $quantity): void
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('On-hand quantity cannot be negative.');
        }

        if (InventoryOwnerScope::isEnabled()) {
            OwnerContext::assertResolvedOrExplicitGlobal(InventoryOwnerScope::resolveOwner());
        }

        DB::transaction(function () use ($item, $quantity): void {
            $location = InventoryLocation::getOrCreateDefault();
            $level = $this->inventory->getOrCreateLevel($item, $location->id);

            $level = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
                ->whereKey($level->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($quantity !== $level->quantity_on_hand) {
                $this->inventory->adjust($item, $location->id, $quantity, reason: 'set-default-inventory-quantity');
            }
        });
    }
}
