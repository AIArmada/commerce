<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Events\EventRegistrationRefunded;
use AIArmada\Events\Models\EventRegistrationItem;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Orders\Models\Order;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Support\Facades\Log;

/**
 * Restores ticket inventory for the admissions that were actually refunded.
 *
 * Orders can contain several admissions. The order refund event therefore
 * remains the financial source of truth, while the registration event restores
 * only the inventory represented by that registration's ticket item.
 */
final class RestoreTicketInventoryOnRegistrationRefunded
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    public function handle(EventRegistrationRefunded $event): void
    {
        if (! (bool) config('inventory.orders.enabled', true)) {
            return;
        }

        $registration = $event->registration;
        $orderId = $registration->external_order_id;

        if (! is_string($orderId) || $orderId === '') {
            return;
        }

        OwnerContext::withOwner(null, function () use ($orderId, $registration): void {
            $order = Order::query()->find($orderId);

            if (! $order instanceof Order || ! is_string($order->order_number) || $order->order_number === '') {
                return;
            }

            $registration->loadMissing('items.ticketType');

            foreach ($registration->items as $item) {
                if (! $item instanceof EventRegistrationItem) {
                    continue;
                }

                $ticketType = $item->ticketType;

                if (! $ticketType instanceof TicketType) {
                    continue;
                }

                $this->restoreItem($order, $registration->getKey(), $item, $ticketType);
            }
        });
    }

    private function restoreItem(
        Order $order,
        string $registrationId,
        EventRegistrationItem $item,
        TicketType $ticketType,
    ): void {
        $reason = 'event_registration_item_refunded:' . $item->getKey();
        $morphType = $ticketType->getMorphClass();

        $restored = (int) InventoryMovement::query()
            ->where('type', MovementType::Receipt->value)
            ->where('reason', $reason)
            ->where('inventoryable_type', $morphType)
            ->where('inventoryable_id', $ticketType->getKey())
            ->sum('quantity');
        $remaining = max(0, (int) $item->quantity - $restored);

        if ($remaining === 0) {
            return;
        }

        $shipments = InventoryMovement::query()
            ->where('type', MovementType::Shipment->value)
            ->whereIn('reference', [$order->getKey(), $order->order_number])
            ->where('inventoryable_type', $morphType)
            ->where('inventoryable_id', $ticketType->getKey())
            ->orderBy('created_at')
            ->get();

        foreach ($shipments as $shipment) {
            if ($remaining === 0 || ! $shipment instanceof InventoryMovement || $shipment->from_location_id === null) {
                continue;
            }

            $quantity = min($remaining, (int) $shipment->quantity);

            if ($quantity < 1) {
                continue;
            }

            $this->inventoryService->receive(
                model: $ticketType,
                locationId: (string) $shipment->from_location_id,
                quantity: $quantity,
                reason: $reason,
                note: sprintf('Restored from refunded event registration %s', $registrationId),
            );

            $remaining -= $quantity;
        }

        if ($remaining > 0) {
            Log::warning('Could not restore all ticket inventory for refunded registration.', [
                'registration_id' => $registrationId,
                'order_id' => $order->getKey(),
                'ticket_type_id' => $ticketType->getKey(),
                'quantity_remaining' => $remaining,
            ]);
        }
    }
}
