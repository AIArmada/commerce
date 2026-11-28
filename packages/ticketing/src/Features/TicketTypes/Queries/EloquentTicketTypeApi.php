<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\TicketTypes\Queries;

use AIArmada\Ticketing\Contracts\TicketableInterface;
use AIArmada\Ticketing\Features\TicketTypes\Api\TicketTypeApi;
use AIArmada\Ticketing\Features\TicketTypes\Api\TicketTypeIssuanceData;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use Illuminate\Database\Eloquent\Relations\Relation;

final readonly class EloquentTicketTypeApi implements TicketTypeApi
{
    public function issuanceData(string $ticketTypeId): TicketTypeIssuanceData
    {
        return $this->describe(TicketType::query()->with('ticketable')->findOrFail($ticketTypeId));
    }

    public function issuanceDataFor(string $ticketableType, string $ticketableId): ?TicketTypeIssuanceData
    {
        if (! $this->isTicketType($ticketableType)) {
            return null;
        }

        $ticketType = TicketType::query()->with('ticketable')->find($ticketableId);

        return $ticketType instanceof TicketType ? $this->describe($ticketType) : null;
    }

    public function isTicketType(string $ticketableType): bool
    {
        $class = Relation::getMorphedModel($ticketableType) ?? $ticketableType;

        return is_string($class) && is_a($class, TicketType::class, true);
    }

    private function describe(TicketType $ticketType): TicketTypeIssuanceData
    {
        $ticketable = $ticketType->ticketable;

        return new TicketTypeIssuanceData(
            id: (string) $ticketType->getKey(),
            ticketableType: (string) $ticketType->ticketable_type,
            ticketableId: (string) $ticketType->ticketable_id,
            transferWindowEndsAt: $ticketable instanceof TicketableInterface
                ? $ticketable->transferWindowEndsAt()
                : null,
        );
    }
}
