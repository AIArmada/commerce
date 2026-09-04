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

            $options = $item->getAttribute('options') ?? [];

            if (is_array($options) && ($options['event_fulfillment'] ?? null) === 'event_registration') {
                continue;
            }

            $legacyAttributes = $item->getAttribute('attributes') ?? [];
            $holderAttributes = is_array($options) && is_array($options['participants'] ?? null)
                ? $options['participants']
                : (is_array($legacyAttributes) ? ($legacyAttributes['participants'] ?? []) : []);

            $context = new PassIssuanceContext(
                ticketType: $ticketType,
                quantity: $item->quantity,
                holderAttributes: $holderAttributes,
                metadata: ['order_id' => $order->getKey(), 'order_item_id' => $item->getKey()],
            );

            app(IssuePassesAction::class)->handle($context);
        }
    }
}
