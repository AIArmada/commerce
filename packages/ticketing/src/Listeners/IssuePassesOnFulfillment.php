<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Ticketing\Actions\IssuePassesAction;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class IssuePassesOnFulfillment
{
    public function handle(OrderFulfillmentRequired $event): void
    {
        $order = $event->order;

        // Relayed fulfillment runs ownerless: restore the order's owner
        // so item reads, the dedup count, and issuance resolve scoped.
        OwnerContext::withOwner($order->owner ?? null, function () use ($order): void {
            foreach ($order->items as $item) {
                $ticketType = $item->purchasable;

                if (! $ticketType instanceof TicketType) {
                    continue;
                }

                $options = $item->getAttribute('options') ?? [];

                if (is_array($options) && ($options['event_fulfillment'] ?? null) === 'event_registration') {
                    continue;
                }

                $this->issueRemainderForItem($order->getKey(), $item, $ticketType);
            }
        });
    }

    private function issueRemainderForItem(mixed $orderId, Model $item, TicketType $ticketType): void
    {
        DB::transaction(function () use ($orderId, $item, $ticketType): void {
            // Serialize overlapping deliveries per item: recount inside
            // the item lock so a live dispatch and a relay redelivery
            // cannot both see the same remainder.
            $locked = $item->newQuery()->whereKey($item->getKey())->lockForUpdate()->first();

            if (! $locked instanceof Model) {
                return;
            }

            // Redelivered fulfillment (outbox relay) must not duplicate
            // passes: issue only the remainder for this item.
            $issued = $this->issuedCount($locked->getKey());
            $quantity = (int) $locked->getAttribute('quantity') - $issued;

            if ($quantity <= 0) {
                return;
            }

            $options = $locked->getAttribute('options') ?? [];
            $holderAttributes = is_array($options) && is_array($options['participants'] ?? null)
                ? $options['participants']
                : [];

            // Remainder issuance continues where the earlier batch left
            // off: skip holder slots already attached to issued passes.
            if (array_is_list($holderAttributes)) {
                $holderAttributes = array_slice($holderAttributes, $issued);
            }

            $context = new PassIssuanceContext(
                ticketType: $ticketType,
                quantity: $quantity,
                holderAttributes: $holderAttributes,
                metadata: ['order_id' => $orderId, 'order_item_id' => $locked->getKey()],
            );

            app(IssuePassesAction::class)->handle($context);
        });
    }

    private function issuedCount(mixed $itemId): int
    {
        // Counts passes in every state, including revoked: redelivery
        // must never re-issue.
        return Pass::query()->where('metadata->order_item_id', (string) $itemId)->count();
    }
}
