<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\EventOrderDriver;
use AIArmada\Events\Contracts\EventTicketingDriver;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use Illuminate\Support\Facades\DB;

/**
 * Restores ticket inventory for the admissions that were actually refunded.
 *
 * Orders can contain several admissions. The order refund event therefore
 * remains the financial source of truth, while the registration event restores
 * only the inventory represented by that registration's ticket item.
 */
final class RestoreTicketInventoryOnRegistrationRefundedAction
{
    public function __construct(
        private readonly RegistrationsApi $registrations,
        private readonly EventOrderDriver $orders,
        private readonly EventTicketingDriver $ticketing,
    ) {}

    public function handle(string $registrationId): void
    {
        if (! (bool) config('inventory.orders.enabled', true)) {
            return;
        }

        $summary = $this->registrations->summarize($registrationId);
        $orderId = $summary?->externalOrderId;

        if ($orderId === null) {
            return;
        }

        DB::transaction(function () use ($registrationId, $orderId): void {
            // Mutex, not authorization: the refund decision already happened
            // upstream. Locking the registration row serializes concurrent
            // deliveries so the already-restored guard inside the ticketing
            // driver computes against settled receipts, and keeps multi-item
            // restores atomic.
            $this->registrations->lock($registrationId);

            $orderNumber = $this->orders->orderNumber($orderId);

            if ($orderNumber === null) {
                return;
            }

            foreach ($this->registrations->orderItemsForRegistration($registrationId) as $item) {
                if ($item->ticketTypeId === null || $item->quantity < 1) {
                    continue;
                }

                $this->ticketing->restoreRefundedRegistrationItemInventory(
                    orderId: $orderId,
                    orderNumber: $orderNumber,
                    registrationItemId: $item->id,
                    ticketTypeId: $item->ticketTypeId,
                    quantity: $item->quantity,
                );
            }
        });
    }
}
