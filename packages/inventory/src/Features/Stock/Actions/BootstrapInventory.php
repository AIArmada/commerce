<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Services\InventoryService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class BootstrapInventory
{
    public function __construct(private InventoryService $inventory) {}

    public function execute(Model $model, int $minimumOnHand, ?string $reason = null, ?string $note = null): void
    {
        if ($minimumOnHand < 1) {
            return;
        }

        if (InventoryOwnerScope::isEnabled()) {
            OwnerContext::assertResolvedOrExplicitGlobal(
                InventoryOwnerScope::resolveOwner(),
                'Inventory mutations require an owner context or explicit global context.',
            );
        }

        DB::transaction(function () use ($model, $minimumOnHand, $reason, $note): void {
            $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();

            if (InventoryOwnerScope::forMutation(InventoryLevel::query())
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->exists()) {
                return;
            }

            $this->inventory->receiveAtDefault($model, $minimumOnHand, $reason, $note);
        }, 3);
    }
}
