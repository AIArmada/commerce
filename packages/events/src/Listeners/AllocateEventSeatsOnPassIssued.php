<?php

declare(strict_types=1);

namespace AIArmada\Events\Listeners;

use AIArmada\Events\Actions\AllocateEventSeatsOnPassIssued as AllocateEventSeatsAction;
use AIArmada\Ticketing\Events\PassIssued;
use AIArmada\Ticketing\Models\TicketType;

final class AllocateEventSeatsOnPassIssued
{
    public function __construct(
        private readonly AllocateEventSeatsAction $allocateAction,
    ) {}

    public function handle(PassIssued $event): void
    {
        $pass = $event->pass;

        $pass->loadMissing('ticketType');

        $ticketType = $pass->ticketType;

        if (! $ticketType instanceof TicketType) {
            return;
        }

        $seatingMode = $ticketType->seating_mode;

        if ($seatingMode === null || ! $seatingMode->requiresAllocation()) {
            return;
        }

        $this->allocateAction->handle($event);
    }
}
