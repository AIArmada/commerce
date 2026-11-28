<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Exceptions\InvalidReservationTransition;
use AIArmada\Inventory\Features\Reservations\Models\InventoryOperation;
use AIArmada\Inventory\Features\Reservations\Models\InventoryReservation;
use AIArmada\Inventory\Features\Reservations\Persistence\CheckoutReservationService;
use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Features\Reservations\Queries\InventoryDemandResolver;
use AIArmada\Inventory\Features\Reservations\Queries\OrderInventoryIdentity;
use AIArmada\Inventory\Features\Stock\Api\DeductionCandidates;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use AIArmada\Orders\Events\InventoryDeductionRequired;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Fraud;
use AIArmada\Orders\States\Refunded;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Deducts inventory when an order payment is confirmed.
 *
 * This listener bridges the orders package with inventory management.
 * It handles two scenarios:
 *
 * 1. **With a checkout reservation**: If checkout reserved the composition,
 *    commit that reservation group so the reserved units become one shipment
 *    deduction and the group reaches its committed state.
 *
 * 2. **Without a reservation**: If the order was created directly without cart
 *    allocations (API, admin, etc.), deduct inventory directly.
 */
final class DeductOrderInventory
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryAllocationService $allocationService,
        private readonly CheckoutReservationService $reservationService,
    ) {}

    public function execute(InventoryDeductionRequired $event): void
    {
        if (! config('inventory.orders.enabled', true)) {
            return;
        }

        // A half-null tuple is corrupt data, not a global order: the
        // resolver maps any null field to global context, so reject the
        // asymmetric shape before it can run as global work.
        if (($event->owner_type === null) !== ($event->owner_id === null)) {
            Log::warning('Inventory deduction skipped: order owner tuple is half-null.', [
                'order_id' => $event->order->getKey(),
            ]);

            return;
        }

        // This chain runs on the queue worker, where no ambient owner
        // survives serialization. Restore the order's owner from the
        // event tuple so owner-scoped lookups resolve correctly.
        try {
            $owner = OwnerContext::fromTypeAndId($event->owner_type, $event->owner_id);
        } catch (InvalidArgumentException $exception) {
            Log::warning('Inventory deduction skipped: order owner tuple is malformed.', [
                'order_id' => $event->order->getKey(),
                'reason' => $exception->getMessage(),
            ]);

            return;
        }

        OwnerContext::withOwner($owner, function () use ($event): void {
            $this->handleScoped($event);
        });
    }

    private function handleScoped(InventoryDeductionRequired $event): void
    {
        $order = OrderInventoryIdentity::find($event);

        if ($order === null) {
            Log::warning('Inventory deduction skipped: persisted order does not match event identity.', [
                'order_id' => $event->order->getKey(),
            ]);

            return;
        }

        $operation = $this->resolveOrCreateOperation($order, InventoryOperation::KIND_DEDUCTION);

        if ($operation->status === InventoryOperation::STATUS_COMPLETED) {
            Log::info('Inventory deduction already completed for order', [
                'order_id' => $order->id,
                'operation_id' => $operation->id,
            ]);

            return;
        }

        DB::transaction(function () use ($event, $order, $operation): void {
            $operation = InventoryOwnerScope::forMutation(
                InventoryOperation::query()
                    ->whereKey($operation->id)
                    ->lockForUpdate()
            )->firstOrFail();

            if ($operation->status === InventoryOperation::STATUS_COMPLETED) {
                return;
            }

            // The event carries a serialized copy: re-read the live
            // lifecycle under a lock so a delayed deduction never
            // consumes stock after a terminal transition. Same terminal
            // set as the outbox relay suppression.
            $fresh = OrderInventoryIdentity::find($event, lock: true);

            if ($fresh === null) {
                throw new RuntimeException('Order identity changed before inventory deduction.');
            }

            if ($fresh->status instanceof Canceled
                || $fresh->status instanceof Refunded
                || $fresh->status instanceof Fraud
            ) {
                Log::info('Inventory deduction skipped: order no longer deductible.', [
                    'order_id' => $order->getKey(),
                ]);

                $operation->update([
                    'status' => InventoryOperation::STATUS_COMPLETED,
                    'completed_at' => CarbonImmutable::now(),
                ]);

                return;
            }

            $order = $fresh;
            $reference = $order->metadata['inventory_reservation_reference'] ?? null;
            $cartId = $this->extractCartId($order);

            if ((is_string($reference) && $this->commitReservation($reference, $order))
                || ($reference === null && $cartId !== null && $this->tryCommitAllocations($cartId, $order))) {
                $operation->update([
                    'status' => InventoryOperation::STATUS_COMPLETED,
                    'completed_at' => CarbonImmutable::now(),
                ]);

                return;
            }

            $this->deductDirectly($order);

            $operation->update([
                'status' => InventoryOperation::STATUS_COMPLETED,
                'completed_at' => CarbonImmutable::now(),
            ]);
        });
    }

    private function resolveOrCreateOperation(Order $order, string $kind): InventoryOperation
    {
        $existing = InventoryOwnerScope::forMutation(InventoryOperation::query())
            ->where('order_id', $order->id)
            ->where('kind', $kind)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): InventoryOperation => InventoryOperation::create([
                'order_id' => $order->id,
                'kind' => $kind,
                'status' => InventoryOperation::STATUS_PENDING,
            ]));
        } catch (QueryException $e) {
            if (! in_array((string) ($e->errorInfo[0] ?? $e->getCode()), ['23000', '23505'], true)) {
                throw $e;
            }

            return InventoryOwnerScope::forMutation(InventoryOperation::query())
                ->where('order_id', $order->id)
                ->where('kind', $kind)
                ->firstOrFail();
        }
    }

    /**
     * Extract cart ID from order metadata.
     */
    private function extractCartId(Order $order): ?string
    {
        $metadata = $order->metadata ?? [];

        return $metadata['cart_id'] ?? null;
    }

    /**
     * Try to commit existing cart allocations.
     */
    private function tryCommitAllocations(string $cartId, Order $order): bool
    {
        if ($this->commitReservation($cartId, $order)) {
            return true;
        }

        $allocations = $this->allocationService->getAllocationsForCart($cartId);

        if ($allocations->isEmpty()) {
            return false;
        }

        $this->allocationService->commit($cartId, $order->id);

        Log::info('Inventory committed from cart allocations', [
            'order_id' => $order->id,
            'cart_id' => $cartId,
            'allocation_count' => $allocations->count(),
        ]);

        return true;
    }

    /**
     * Commit the checkout reservation that owns this cart, when one exists and
     * still holds stock. An expired reservation has already released its units,
     * so the caller must fall back to direct deduction.
     */
    private function commitReservation(string $cartId, Order $order): bool
    {
        try {
            $outcome = $this->reservationService->commit($cartId, (string) $order->getKey());
        } catch (InvalidReservationTransition) {
            $this->reservationService->find($cartId);

            return false;
        }

        if ($outcome->state !== InventoryReservation::STATE_COMMITTED) {
            return false;
        }

        Log::info('Inventory committed from checkout reservation', [
            'order_id' => $order->id,
            'cart_id' => $cartId,
        ]);

        return true;
    }

    /**
     * Deduct inventory directly for orders without allocations.
     */
    private function deductDirectly(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->loadMissing('items.purchasable');

            $demand = InventoryDemandResolver::forOrder($order->items);

            $identities = [];

            foreach ($demand as $key => $entry) {
                $identities[$key] = [
                    'inventoryable_type' => $entry['model']->getMorphClass(),
                    'inventoryable_id' => (string) $entry['model']->getKey(),
                ];
            }

            $availability = app(DeductionCandidates::class)->locked(array_values($identities));
            $preferredLocation = $order->metadata['fulfillment_location_id'] ?? null;
            $plan = [];

            foreach ($demand as $key => $entry) {
                $slots = $availability[$key] ?? [];
                $quantity = $entry['quantity'];
                $locationId = null;

                if (is_string($preferredLocation)) {
                    foreach ($slots as $slot) {
                        if ($slot['location_id'] === $preferredLocation && $slot['available'] >= $quantity) {
                            $locationId = $slot['location_id'];

                            break;
                        }
                    }
                }

                if ($locationId === null) {
                    foreach ($slots as $slot) {
                        if ($slot['available'] >= $quantity) {
                            $locationId = $slot['location_id'];

                            break;
                        }
                    }
                }

                if ($locationId === null) {
                    throw new RuntimeException('Insufficient inventory to deduct the complete order.');
                }

                $plan[] = ['model' => $entry['model'], 'location_id' => $locationId, 'quantity' => $quantity];
            }

            foreach ($plan as $shipment) {
                $this->inventoryService->ship(
                    model: $shipment['model'],
                    locationId: $shipment['location_id'],
                    quantity: $shipment['quantity'],
                    reason: 'order',
                    reference: (string) $order->getKey(),
                    note: sprintf('Order #%s', $order->order_number),
                );
            }
        });

        Log::info('Inventory deducted directly for order', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'item_count' => $order->items->count(),
        ]);
    }
}
