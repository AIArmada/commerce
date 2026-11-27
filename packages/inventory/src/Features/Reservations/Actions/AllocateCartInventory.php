<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Inventory\Features\Reservations\Models\InventoryAllocation;
use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Features\Reservations\Queries\CartReservationIdentity;
use AIArmada\Inventory\Features\Reservations\Queries\InventoryDemandResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class AllocateCartInventory
{
    public function __construct(private InventoryAllocationService $allocationService) {}

    /** @return array<string, Collection<int, InventoryAllocation>> */
    public function execute(Cart $cart, int $ttlMinutes = 30): array
    {
        return DB::transaction(function () use ($cart, $ttlMinutes): array {
            $results = [];
            $cartIdentifier = CartReservationIdentity::fromCart($cart);

            foreach (InventoryDemandResolver::forCart($cart->getItems()) as $entry) {
                $allocations = $this->allocationService->allocate(
                    $entry['model'],
                    $entry['quantity'],
                    $cartIdentifier,
                    $ttlMinutes
                );

                foreach (array_keys($entry['sources']) as $itemId) {
                    $existing = $results[$itemId] ?? new Collection;
                    $results[$itemId] = $existing->merge($allocations);
                }
            }

            return $results;
        });
    }
}
