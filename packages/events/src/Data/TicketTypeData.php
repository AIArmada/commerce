<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Ticketing\Models\TicketType;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class TicketTypeData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $code,
        public readonly string | null | Optional $description,
        public readonly string $access_type,
        public readonly string | null | Optional $seating_mode,
        public readonly int $price,
        public readonly string $currency,
        public readonly int | null | Optional $quota,
        public readonly int $admits_quantity,
        public readonly string $status,
        public readonly CarbonImmutable | null | Optional $sales_starts_at,
        public readonly CarbonImmutable | null | Optional $sales_ends_at,
    ) {}

    public static function fromTicketType(TicketType $ticketType): self
    {
        $quota = $ticketType->inventoryLevels()->exists()
            ? $ticketType->getTotalOnHand()
            : null;

        return new self(
            id: $ticketType->id,
            name: $ticketType->name,
            code: $ticketType->code,
            description: $ticketType->description,
            access_type: $ticketType->access_type,
            seating_mode: $ticketType->seating_mode,
            price: $ticketType->price,
            currency: $ticketType->currency,
            quota: $quota,
            admits_quantity: $ticketType->admits_quantity,
            status: $ticketType->status,
            sales_starts_at: $ticketType->sales_starts_at,
            sales_ends_at: $ticketType->sales_ends_at,
        );
    }
}
