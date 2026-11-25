<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Queries;

use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Features\Registrations\Api\EventRegistrationParticipantData;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;

final class DefaultEventOrderItemFulfillmentResolver implements EventOrderItemFulfillmentResolver
{
    public function __construct(
        private readonly RegistrationsApi $registrations,
    ) {}

    public function resolve(string $registrationItemId): mixed
    {
        $item = $this->registrations->findOrderItem($registrationItemId);

        if ($item === null) {
            return null;
        }

        $participants = $this->registrations->participantsForRegistration($item->registrationId);

        if ($participants === []) {
            return null;
        }

        $summary = $this->registrations->summarize($item->registrationId);

        return [
            'event_id' => $item->eventId,
            'event_occurrence_id' => $summary?->occurrenceId,
            'ticket_type_id' => $item->ticketTypeId,
            'participants' => array_values(array_map(
                static fn (EventRegistrationParticipantData $participant): array => array_filter([
                    'name' => $participant->name,
                    'email' => $participant->email,
                    'phone' => $participant->phone,
                    'is_primary' => $participant->isPrimary,
                ], static fn (mixed $value): bool => $value !== null),
                $participants,
            )),
        ];
    }
}
