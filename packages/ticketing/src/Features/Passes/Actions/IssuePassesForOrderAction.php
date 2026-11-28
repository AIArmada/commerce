<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use Illuminate\Database\Eloquent\Model;

final readonly class IssuePassesForOrderAction
{
    public function __construct(private IssuePassesAction $issuePasses) {}

    public function handle(Order $order): void
    {
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
        $item->getConnection()->transaction(function () use ($orderId, $item, $ticketType): void {
            $locked = $item->newQuery()->whereKey($item->getKey())->lockForUpdate()->first();

            if (! $locked instanceof Model) {
                return;
            }

            $issued = $this->issuedCount($locked->getKey());
            $quantity = (int) $locked->getAttribute('quantity') - $issued;

            if ($quantity <= 0) {
                return;
            }

            $options = $locked->getAttribute('options') ?? [];
            $holderAttributes = is_array($options) && is_array($options['participants'] ?? null)
                ? $options['participants']
                : [];

            if (array_is_list($holderAttributes)) {
                $holderAttributes = array_slice($holderAttributes, $issued);
            }

            $context = new PassIssuanceContext(
                ticketType: $ticketType,
                quantity: $quantity,
                holderAttributes: $holderAttributes,
                metadata: ['order_id' => $orderId, 'order_item_id' => $locked->getKey()],
            );

            $this->issuePasses->handle($context);
        });
    }

    private function issuedCount(mixed $itemId): int
    {
        return Pass::query()->where('metadata->order_item_id', (string) $itemId)->count();
    }
}
