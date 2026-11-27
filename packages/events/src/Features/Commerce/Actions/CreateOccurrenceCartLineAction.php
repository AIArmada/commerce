<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Runtime\CartManagerInterface;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Ticketing\Models\TicketType;
use InvalidArgumentException;
use RuntimeException;

final class CreateOccurrenceCartLineAction
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly AddEventTicketTypeToCartAction $addToCart,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(string $scopeType, string $scopeId, array $data = []): void
    {
        $ticketTypeId = $data['ticket_type_id'] ?? null;

        if (! is_string($ticketTypeId) || $ticketTypeId === '') {
            throw new InvalidArgumentException('Missing ticket_type_id in data.');
        }

        $scope = $this->catalog->describeScope($scopeType, $scopeId);

        if ($scope === null) {
            throw new InvalidArgumentException(sprintf('Unknown event scope [%s:%s].', $scopeType, $scopeId));
        }

        /** @var TicketType|null $ticketType */
        $ticketType = TicketType::query()
            ->where('ticketable_type', $scope->morphClass)
            ->where('ticketable_id', $scopeId)
            ->whereKey($ticketTypeId)
            ->first();

        if (! $ticketType instanceof TicketType) {
            throw new InvalidArgumentException(sprintf(
                'Ticket type %s not found for the selected event scope %s.',
                $ticketTypeId,
                $scopeId,
            ));
        }

        $cart = app(CartManagerInterface::class)->getCurrentCart();

        if (! $cart instanceof Cart) {
            throw new RuntimeException('The current cart could not be resolved.');
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $participants = $data['participants'] ?? [];

        $this->addToCart->handle(
            cart: $cart,
            ticketType: $ticketType,
            quantity: $quantity,
            participants: is_array($participants) ? $participants : [],
        );
    }
}
