<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Actions;

use AIArmada\Inventory\Contracts\BatchReservationInvalidator;
use AIArmada\Inventory\Contracts\CheckoutReservationServiceInterface;
use AIArmada\Inventory\Features\Reservations\Models\InventoryAllocation;
use AIArmada\Inventory\Features\Reservations\Models\InventoryReservation;
use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class InvalidateBatchReservations implements BatchReservationInvalidator
{
    public function __construct(
        private InventoryAllocationService $allocations,
        private CheckoutReservationServiceInterface $checkout,
    ) {}

    public function releaseForBatch(string $batchId): void
    {
        DB::transaction(function () use ($batchId): void {
            $allocations = InventoryOwnerScope::forMutation(InventoryAllocation::query())
                ->where('batch_id', $batchId)
                ->orderBy('id')
                ->get();
            $groups = [];

            foreach ($allocations as $allocation) {
                if ($allocation->reservation_group_id === null) {
                    $this->allocations->releaseAllocation($allocation);

                    continue;
                }

                $groups[$allocation->reservation_group_id] = true;
            }

            foreach (array_keys($groups) as $id) {
                $group = InventoryOwnerScope::forMutation(InventoryReservation::query())->whereKey($id)->firstOrFail();
                $outcome = $this->checkout->release($group->reference);

                if (! in_array($outcome->state, [InventoryReservation::STATE_RELEASED, InventoryReservation::STATE_EXPIRED], true)) {
                    throw new InvalidArgumentException('Unable to release the affected checkout reservation');
                }
            }
        });
    }
}
