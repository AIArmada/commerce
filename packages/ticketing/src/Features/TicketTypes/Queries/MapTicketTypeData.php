<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\TicketTypes\Queries;

use AIArmada\Ticketing\Data\TicketTypeData;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;

final readonly class MapTicketTypeData
{
    public function handle(TicketType $ticketType): TicketTypeData
    {
        $quota = class_exists('AIArmada\\Inventory\\Models\\InventoryLevel')
            ? $ticketType->inventoryLevels()->selectRaw('SUM(quantity_on_hand) as quota')->value('quota')
            : null;
        $quota = $quota === null ? null : (int) $quota;

        return new TicketTypeData(
            name: $ticketType->name,
            code: $ticketType->code,
            id: (string) $ticketType->getKey(),
            description: $ticketType->description,
            access_type: $ticketType->access_type,
            seating_mode: $ticketType->seating_mode?->value,
            price: $ticketType->price,
            currency: $ticketType->currency,
            admits_quantity: $ticketType->admits_quantity,
            min_quantity: $ticketType->min_quantity,
            max_quantity: $ticketType->max_quantity,
            sales_starts_at: $ticketType->sales_starts_at,
            sales_ends_at: $ticketType->sales_ends_at,
            status: (string) $ticketType->status,
            visibility: $ticketType->visibility->value,
            sort_order: $ticketType->sort_order,
            metadata: $ticketType->metadata,
            quota: $quota,
        );
    }
}
