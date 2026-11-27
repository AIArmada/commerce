<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Features\Reservations\Queries\CartReservationIdentity;
use AIArmada\Inventory\Features\Reservations\Queries\InventoryDemandResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class ReserveCartInventory
{
    private const string ALLOCATION_METADATA_KEY = 'inventory_allocations';

    public function __construct(private InventoryAllocationService $allocationService) {}

    public function execute(Cart $cart): void
    {
        $cartId = CartReservationIdentity::fromCart($cart);
        $ttlMinutes = (int) config('inventory.allocation_ttl_minutes', 30);
        $demand = InventoryDemandResolver::forCart($cart->getItems());
        $reservedAt = CarbonImmutable::now();

        [$allocations, $errors] = DB::transaction(function () use ($demand, $cartId, $ttlMinutes, $reservedAt): array {
            $allocations = [];
            $errors = [];

            foreach ($demand as $entry) {
                try {
                    $itemAllocations = $this->allocationService->allocate(
                        $entry['model'],
                        $entry['quantity'],
                        $cartId,
                        $ttlMinutes,
                    );

                    if ($itemAllocations->isEmpty()) {
                        continue;
                    }

                    foreach ($entry['sources'] as $itemId => $source) {
                        $allocations[$itemId] = [
                            'item_id' => $itemId,
                            'item_name' => $source['name'],
                            'quantity' => $source['quantity'],
                            'allocation_count' => $itemAllocations->count(),
                            'allocated_at' => $reservedAt->toISOString(),
                        ];
                    }
                } catch (InsufficientInventoryException $exception) {
                    if (config('inventory.cart.block_checkout_on_insufficient', true)) {
                        throw $exception;
                    }

                    foreach (array_keys($entry['sources']) as $itemId) {
                        $errors[$itemId] = $exception->getMessage();
                    }
                }
            }

            return [$allocations, $errors];
        });

        if ($allocations !== [] || $errors !== []) {
            $cart->setMetadata(self::ALLOCATION_METADATA_KEY, [
                'allocations' => $allocations,
                'errors' => $errors,
                'reserved_at' => $reservedAt->toISOString(),
                'expires_at' => $reservedAt->addMinutes($ttlMinutes)->toISOString(),
            ]);
        }
    }
}
