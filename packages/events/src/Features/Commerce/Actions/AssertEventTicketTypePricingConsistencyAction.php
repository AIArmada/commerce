<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Exceptions\InconsistentTicketTypePricingException;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Ticketing\Data\PricingMode;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class AssertEventTicketTypePricingConsistencyAction
{
    public function __construct(
        private readonly CatalogApi $catalog,
    ) {}

    public function handle(string $ticketTypeId): void
    {
        /** @var TicketType|null $ticketType */
        $ticketType = TicketType::query()->whereKey($ticketTypeId)->first();

        if (! $ticketType instanceof TicketType) {
            return;
        }

        try {
            $ticketType->loadMissing('ticketable');
        } catch (Throwable) {
            return;
        }

        $ticketable = $ticketType->ticketable;

        if (! $ticketable instanceof Model) {
            return;
        }

        $scopeType = $this->scopeType($ticketable);

        if ($scopeType === null) {
            return;
        }

        $pricingMode = $this->catalog->describeScope($scopeType, (string) $ticketable->getKey())?->pricingMode;

        if ($pricingMode === null) {
            return;
        }

        $isPaid = (int) $ticketType->price > 0;
        $isFree = (int) $ticketType->price === 0;

        if ($pricingMode === PricingMode::Free->value && $isPaid) {
            throw new InconsistentTicketTypePricingException(
                "Ticket type {$ticketType->id} cannot have a non-zero price in a free pricing scope.",
            );
        }

        if ($pricingMode === PricingMode::Paid->value && $isFree) {
            throw new InconsistentTicketTypePricingException(
                "Ticket type {$ticketType->id} cannot have a zero price in a paid pricing scope.",
            );
        }
    }

    private function scopeType(Model $ticketable): ?string
    {
        $eventClass = ModelResolver::eventClass();
        $occurrenceClass = ModelResolver::occurrenceClass();
        $sessionClass = ModelResolver::sessionClass();

        return match (true) {
            $ticketable instanceof $sessionClass => 'session',
            $ticketable instanceof $occurrenceClass => 'occurrence',
            $ticketable instanceof $eventClass => 'event',
            default => null,
        };
    }
}
