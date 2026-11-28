<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reservations\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Features\Reservations\Models\InventoryOperation;
use AIArmada\Inventory\Features\Reservations\Models\InventoryReservation;
use AIArmada\Inventory\Features\Reservations\Persistence\CheckoutReservationService;
use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Features\Stock\Api\RestoreOrderStock;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use AIArmada\Orders\Events\InventoryReleaseRequired;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Releases inventory when an order is cancelled.
 *
 * This listener handles two scenarios:
 *
 * 1. **With cart allocations**: Release any pending allocations that
 *    haven't been committed yet.
 *
 * 2. **After shipment**: If inventory was already deducted, create
 *    a return movement to restore stock.
 */
final class ReleaseOrderInventory
{
    public function __construct(
        private readonly InventoryAllocationService $allocationService,
    ) {}

    public function execute(InventoryReleaseRequired $event): void
    {
        if (! config('inventory.orders.enabled', true)) {
            return;
        }

        // A half-null tuple is corrupt data, not a global order: the
        // resolver maps any null field to global context, so reject the
        // asymmetric shape before it can run as global work.
        if (($event->owner_type === null) !== ($event->owner_id === null)) {
            Log::warning('Inventory release skipped: order owner tuple is half-null.', [
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
            Log::warning('Inventory release skipped: order owner tuple is malformed.', [
                'order_id' => $event->order->getKey(),
                'reason' => $exception->getMessage(),
            ]);

            return;
        }

        OwnerContext::withOwner($owner, function () use ($event): void {
            $this->handleScoped($event);
        });
    }

    private function handleScoped(InventoryReleaseRequired $event): void
    {
        $order = $event->order;

        $operation = $this->resolveOrCreateOperation($order, InventoryOperation::KIND_RELEASE);

        if ($operation->status === InventoryOperation::STATUS_COMPLETED) {
            Log::info('Inventory release already completed for order', [
                'order_id' => $order->id,
                'operation_id' => $operation->id,
            ]);

            return;
        }

        DB::transaction(function () use ($order, $operation): void {
            $operation = InventoryOwnerScope::forMutation(
                InventoryOperation::query()
                    ->whereKey($operation->id)
                    ->lockForUpdate()
            )->firstOrFail();

            if ($operation->status === InventoryOperation::STATUS_COMPLETED) {
                return;
            }

            $fresh = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $this->releaseAllocations($fresh);
            $this->restoreShippedInventory($fresh);

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
     * Release any pending cart allocations.
     */
    private function releaseAllocations(Order $order): void
    {
        $reference = $order->metadata['inventory_reservation_reference'] ?? null;
        if (is_string($reference)) {
            $reservations = app(CheckoutReservationService::class);

            if ($reservations->find($reference)->state === InventoryReservation::STATE_RESERVED) {
                $reservations->release($reference);
            }

            return;
        }

        $cartId = $this->extractCartId($order);

        if ($cartId === null) {
            return;
        }

        $released = $this->allocationService->releaseAllForCart($cartId);

        if ($released > 0) {
            Log::info('Released pending inventory allocations for cancelled order', [
                'order_id' => $order->id,
                'cart_id' => $cartId,
                'quantity_released' => $released,
            ]);
        }
    }

    /**
     * Restore inventory that was already shipped/deducted.
     */
    private function restoreShippedInventory(Order $order): void
    {
        $restored = app(RestoreOrderStock::class)->restore((string) $order->getKey(), $order->order_number);

        if ($restored > 0) {
            Log::info('Restored inventory for cancelled order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'movement_count' => $restored,
            ]);
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
}
