<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Ticketing\Models\TicketType;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateOccurrenceCartLineAction
{
    use AsAction;

    /**
     * @param  array{ticket_type_id?: string, quantity?: int, participants?: array<int, array<string, mixed>>}  $data
     */
    public function handle(EventOccurrence | EventSession $target, array $data = []): mixed
    {
        $ticketTypeId = $data['ticket_type_id'] ?? null;

        if ($ticketTypeId === null) {
            throw new InvalidArgumentException('Missing ticket_type_id in data.');
        }

        /** @var TicketType|null $ticketType */
        $ticketType = TicketType::query()
            ->whereMorphedTo('ticketable', $target)
            ->whereKey($ticketTypeId)
            ->first();

        if ($ticketType === null) {
            throw new InvalidArgumentException(sprintf(
                'Ticket type %s not found for the selected event scope %s.',
                $ticketTypeId,
                $target->id,
            ));
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $participants = $data['participants'] ?? [];

        $cart = app(CartManagerInterface::class)->getCurrentCart();

        return AddEventTicketTypeToCartAction::make()->handle(
            cart: $cart,
            ticketType: $ticketType,
            quantity: $quantity,
            participants: is_array($participants) ? $participants : [],
        );
    }
}
