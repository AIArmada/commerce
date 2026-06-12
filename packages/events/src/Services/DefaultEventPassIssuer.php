<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Contracts\EventPassIssuer;
use AIArmada\Events\Events\EventPassIssued;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventPass;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventTicketType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DefaultEventPassIssuer implements EventPassIssuer
{
    public function issuePassesFor(EventRegistration $registration): iterable
    {
        OwnerWriteGuard::findOrFailForOwner(Event::class, $registration->event_id);

        $registration->loadMissing('items.ticketType');

        $passes = [];

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
                    'pass_no' => 'PASS-' . strtoupper(Str::random(10)),
                    'status' => 'issued',
                    'issued_at' => CarbonImmutable::now(),
                ]);

                event(new EventPassIssued($pass));
                $passes[] = $pass;
            }
        }

        return $passes;
    }
}
