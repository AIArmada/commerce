<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventPassIssuer;
use AIArmada\Events\Events\EventPassIssued;
use AIArmada\Events\Models\EventPass;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Seating\Contracts\SeatAllocatorInterface;
use AIArmada\Seating\Models\SeatAllocation;
use AIArmada\Seating\Models\SeatHold;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DefaultEventPassIssuer implements EventPassIssuer
{
    public function __construct(
        private readonly SeatAllocatorInterface $seatAllocator,
    ) {}

    public function issuePassesFor(EventRegistration $registration): iterable
    {
        EventWriteGuard::findOrFail($registration->event_id);

        $registration->loadMissing('items.ticketType');

        $passes = [];

        if ($registration->items->isEmpty()) {
            $pass = EventPass::query()->create([
                'event_id' => $registration->event_id,
                'event_occurrence_id' => $registration->event_occurrence_id,
                'event_session_id' => $registration->event_session_id,
                'event_registration_id' => $registration->id,
                'pass_no' => 'PASS-' . mb_strtoupper(Str::random(10)),
                'status' => 'issued',
                'issued_at' => CarbonImmutable::now(),
            ]);

            $this->allocateForPass($pass);

            event(new EventPassIssued($pass));

            return [$pass];
        }

        foreach ($registration->items as $item) {
            $ticketType = $item->ticketType;

            if (! $ticketType instanceof EventTicketType || $ticketType->event_id !== $registration->event_id) {
                throw new InvalidArgumentException('Registration items must reference a ticket type that belongs to the same event.');
            }

            $admits = $ticketType?->admits_quantity ?? 1;
            $quantity = $item->quantity * $admits;

            for ($i = 0; $i < $quantity; $i++) {
                $pass = EventPass::query()->create([
                    'event_id' => $registration->event_id,
                    'event_occurrence_id' => $registration->event_occurrence_id,
                    'event_session_id' => $registration->event_session_id,
                    'event_registration_id' => $registration->id,
                    'event_registration_item_id' => $item->id,
                    'event_ticket_type_id' => $item->event_ticket_type_id,
                    'pass_no' => 'PASS-' . mb_strtoupper(Str::random(10)),
                    'status' => 'issued',
                    'issued_at' => CarbonImmutable::now(),
                ]);

                $this->allocateForPass($pass);

                event(new EventPassIssued($pass));
                $passes[] = $pass;
            }
        }

        return $passes;
    }

    private function allocateForPass(EventPass $pass): void
    {
        $map = $pass->session?->seatMaps()->where('status', 'active')->first()
            ?? $pass->occurrence?->seatMaps()->where('status', 'active')->first()
            ?? $pass->event?->seatMaps()->where('status', 'active')->first();

        if ($map === null) {
            return;
        }

        $results = $this->seatAllocator->allocate(
            map: $map,
            quantity: 1,
            reference: $pass->id,
        );

        foreach ($results as $result) {
            if ($result->holdId === null) {
                continue;
            }

            $hold = SeatHold::query()->find($result->holdId);

            if ($hold === null) {
                continue;
            }

            SeatAllocation::query()->create([
                'seat_id' => $result->seatId,
                'allocated_to_type' => $pass->getMorphClass(),
                'allocated_to_id' => $pass->id,
                'reference' => $pass->pass_no,
                'allocated_at' => now(),
                'state' => 'active',
            ]);

            $hold->markConverted();
        }
    }
}
