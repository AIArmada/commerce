<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\Events\Enums\PricingMode;
use AIArmada\Events\Exceptions\InconsistentTicketTypePricingException;
use AIArmada\Events\Models\EventTicketType;

final class ObserveEventTicketTypePricingConsistency
{
    public function saved(EventTicketType $ticketType): void
    {
        $pricingMode = match (true) {
            $ticketType->session !== null => $ticketType->session->effectivePricingMode(),
            $ticketType->occurrence !== null => $ticketType->occurrence->effectivePricingMode(),
            default => $ticketType->event?->effectivePricingMode(),
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
