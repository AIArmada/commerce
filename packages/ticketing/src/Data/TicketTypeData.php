<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Data;

use AIArmada\Ticketing\Models\TicketType;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class TicketTypeData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly ?string $id = null,
        public readonly ?string $description = null,
        public readonly ?string $access_type = null,
        public readonly ?string $seating_mode = null,
        public readonly ?int $price = null,
        public readonly ?string $currency = null,
        public readonly ?int $quota = null,
        public readonly int $admits_quantity = 1,
        public readonly ?int $min_quantity = null,
        public readonly ?int $max_quantity = null,
        public readonly ?CarbonImmutable $sales_starts_at = null,
        public readonly ?CarbonImmutable $sales_ends_at = null,
        public readonly string $status = 'active',
        public readonly string $visibility = 'public',
        public readonly int $sort_order = 0,
        public readonly ?array $metadata = null,
    ) {}

    public static function fromTicketType(TicketType $ticketType): self
    {
        $quota = class_exists('AIArmada\\Inventory\\Models\\InventoryLevel')
            && $ticketType->inventoryLevels()->exists()
            ? $ticketType->getTotalOnHand()
            : null;

        return new self(
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
