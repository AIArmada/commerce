<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Seating\Actions\ConvertHoldsToAllocationsAction;
use AIArmada\Seating\Actions\EnsureSeatHoldAction;
use AIArmada\Seating\Actions\EnsureSectionAllocationAction;
use AIArmada\Seating\Enums\SeatingMode;
use AIArmada\Seating\Exceptions\SeatMapNotFoundException;
use AIArmada\Seating\Exceptions\SeatSectionNotFoundException;
use AIArmada\Seating\Models\SeatSection;
use AIArmada\Ticketing\Events\PassIssued;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Models\TicketTypeSeatingOption;
use Illuminate\Support\Facades\DB;

class AllocateEventSeatsOnPassIssued
{
    public function __construct(
        private readonly EnsureSeatHoldAction $ensureSeatHold,
        private readonly EnsureSectionAllocationAction $ensureSectionAllocation,
        private readonly ConvertHoldsToAllocationsAction $convertHolds,
        private readonly ResolveEventScopeSeatMapAction $resolveScopeMap,
    ) {}

    public function handle(PassIssued $event): void
    {
        $pass = $event->pass;
        $ticketType = $pass->ticketType;

        if (! $ticketType instanceof TicketType) {
            return;
        }

        $seatingMode = $ticketType->seating_mode;

        if ($seatingMode === null || ! $seatingMode->requiresAllocation()) {
            return;
        }

        $map = $this->resolveScopeMap->handle($pass);

        if ($map === null) {
            throw new SeatMapNotFoundException(
                'No active seat map found for the event scope.'
            );
        }

        DB::transaction(function () use ($pass, $ticketType, $seatingMode, $map): void {
            if ($seatingMode === SeatingMode::GeneralAdmission) {
                $ticketType->loadMissing('seatingOptions.section');
                $option = $ticketType->seatingOptions->first();

                if ($option === null || ! $option instanceof TicketTypeSeatingOption) {
                    throw new SeatSectionNotFoundException(
                        'No seating option configured for this ticket type.'
                    );
                }

                $section = $option->section;

                if (! $section instanceof SeatSection) {
                    throw new SeatSectionNotFoundException(
                        'No seat section found for the configured seating option.'
                    );
                }

                $this->ensureSectionAllocation->handle(
                    section: $section,
                    allocToType: $pass->getMorphClass(),
                    allocToId: $pass->getKey(),
                    reference: $pass->pass_no,
                );

                return;
            }

            $holds = $this->ensureSeatHold->handle(
                map: $map,
                quantity: 1,
                mode: $seatingMode,
                heldByType: $pass->getMorphClass(),
                heldById: $pass->getKey(),
                reference: $pass->id,
            );

            $this->convertHolds->handle(
                holds: $holds,
                mode: $seatingMode,
                allocToType: $pass->getMorphClass(),
                allocToId: $pass->getKey(),
                reference: $pass->pass_no,
            );
        });
    }
}
