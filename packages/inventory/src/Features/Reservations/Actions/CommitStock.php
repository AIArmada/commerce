<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Actions;

use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use Lorisleiva\Actions\Concerns\AsAction;

final class CommitStock
{
    use AsAction;

    public function __construct(
        private readonly InventoryAllocationService $allocationService,
    ) {}

    /**
     * @return array<InventoryMovement>
     */
    public function handle(string $cartId, ?string $orderId = null): array
    {
        return $this->allocationService->commit($cartId, $orderId);
    }
}
