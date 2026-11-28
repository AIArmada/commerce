<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Actions;

use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Features\Stock\Api\CommittedStockMovement;
use Lorisleiva\Actions\Concerns\AsAction;

final class CommitStock
{
    use AsAction;

    public function __construct(
        private readonly InventoryAllocationService $allocationService,
    ) {}

    /**
     * @return list<CommittedStockMovement>
     */
    public function handle(string $cartId, ?string $orderId = null): array
    {
        return $this->allocationService->commit($cartId, $orderId);
    }
}
