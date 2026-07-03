<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\Events\Enums\PricingMode;
use AIArmada\Events\Exceptions\InconsistentTicketTypePricingException;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Ticketing\Models\TicketType;

final class ObserveEventTicketTypePricingConsistency
{
    public function saved(TicketType $ticketType): void
    {
        try {
            $ticketType->loadMissing('ticketable');
        } catch (\Throwable) {
            return;
        }

        $ticketable = $ticketType->ticketable;

        if ($ticketable === null) {
            return;
        }

        $pricingMode = match (true) {
            $ticketable instanceof EventSession => $ticketable->effectivePricingMode(),
            $ticketable instanceof EventOccurrence => $ticketable->effectivePricingMode(),
            $ticketable instanceof Event => $ticketable->effectivePricingMode(),
            default => null,
        };

        if ($pricingMode === null) {
            return;
        }

        $isPaid = (float) $ticketType->price > 0;
        $isFree = (float) $ticketType->price === 0.0;

        if ($pricingMode->isFreeOnly() && $isPaid) {
            throw new InconsistentTicketTypePricingException(
                "Ticket type {$ticketType->id} cannot have a non-zero price in a free pricing scope.",
            );
        }

        if ($pricingMode === PricingMode::Paid && $isFree) {
            throw new InconsistentTicketTypePricingException(
                "Ticket type {$ticketType->id} cannot have a zero price in a paid pricing scope.",
            );
        }
    }
}
