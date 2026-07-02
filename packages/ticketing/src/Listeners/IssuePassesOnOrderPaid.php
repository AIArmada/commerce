<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Listeners;

use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Ticketing\Actions\IssuePassesAction;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Support\PassIssuanceContext;

final class IssuePassesOnOrderPaid
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        foreach ($order->items as $item) {
            $ticketType = $item->purchasable;

            if (! $ticketType instanceof TicketType) {
                continue;
            }

            $attributes = $item->getAttribute('attributes') ?? [];

            $context = new PassIssuanceContext(
                ticketType: $ticketType,
                quantity: $item->quantity,
                holderAttributes: $attributes['participants'] ?? [],
                metadata: ['order_id' => $order->getKey(), 'order_item_id' => $item->getKey()],
            );

            app(IssuePassesAction::class)->handle($context);
        }
    }
}
