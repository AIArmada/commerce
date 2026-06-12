<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventTicketType;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateOccurrenceCartLineAction
{
    use AsAction;

    /**
     * @param  array{event_ticket_type_id?: string, quantity?: int, participants?: array<int, array<string, mixed>>}  $data
     */
    public function handle(EventOccurrence $occurrence, array $data = []): mixed
    {
        $ticketTypeId = $data['event_ticket_type_id'] ?? null;

        if ($ticketTypeId === null) {
            throw new InvalidArgumentException('Missing event_ticket_type_id in data.');
        }

        /** @var EventTicketType|null $ticketType */
        $ticketType = EventTicketType::query()
            ->where('event_occurrence_id', $occurrence->id)
            ->whereKey($ticketTypeId)
            ->first();

        if ($ticketType === null) {
            throw new InvalidArgumentException(sprintf(
                'Ticket type %s not found for occurrence %s.',
                $ticketTypeId,
                $occurrence->id,
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
