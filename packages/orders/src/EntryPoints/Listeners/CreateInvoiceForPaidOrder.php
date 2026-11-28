<?php

declare(strict_types=1);

namespace AIArmada\Orders\EntryPoints\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Contracts\OrderDocuments;
use AIArmada\Orders\Events\OrderPaid;

final readonly class CreateInvoiceForPaidOrder
{
    public function __construct(
        private OrderDocuments $creator
    ) {}

    public function handle(OrderPaid $event): void
    {
        if (! config('orders.integrations.docs.enabled', false)) {
            return;
        }

        if (! $event->order->isFullyPaid()) {
            return;
        }

        $owner = OwnerContext::fromTypeAndIdOrFail($event->order->owner_type, $event->order->owner_id);

        OwnerContext::withOwner($owner, fn (): ?string => $this->creator->issue((string) $event->order->getKey(), 'invoice', $event->transactionId, $event->gateway));
    }
}
