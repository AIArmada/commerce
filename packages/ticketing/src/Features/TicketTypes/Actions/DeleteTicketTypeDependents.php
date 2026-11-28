<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\TicketTypes\Actions;

use AIArmada\Ticketing\Features\Passes\Api\PassesApi;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketTypeComponent;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketTypeProduct;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketTypeSeatingOption;
use Illuminate\Database\Eloquent\Builder;

final readonly class DeleteTicketTypeDependents
{
    public function __construct(private PassesApi $passes) {}

    public function handle(TicketType $ticketType): void
    {
        $key = (string) $ticketType->getKey();

        $this->passes->detachPassesFromTicketType($key, $ticketType->owner_type, $ticketType->owner_id);

        TicketTypeComponent::query()
            ->where(fn (Builder $query): Builder => $query->where('parent_ticket_type_id', $key)
                ->orWhere('component_ticket_type_id', $key))
            ->delete();

        TicketTypeProduct::query()->where('ticket_type_id', $key)->delete();
        TicketTypeSeatingOption::query()->where('ticket_type_id', $key)->delete();
    }
}
