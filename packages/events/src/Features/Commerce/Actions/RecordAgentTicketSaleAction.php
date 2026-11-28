<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Commerce\Actions;

use AIArmada\Events\Contracts\EventPassIssuanceService;
use AIArmada\Events\Contracts\EventRegistrationEligibility;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\EventTicketingDriver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Data\EventRegistrationScopeData;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Platform\EventTicketScope;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecordAgentTicketSaleAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrations,
        private readonly RegistrationsApi $registrationsApi,
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly EventRegistrationEligibility $eligibility,
        private readonly EventTicketingDriver $ticketing,
        private readonly EventPassIssuanceService $passes,
    ) {}

    /**
     * @param  array<string, mixed>|null  $customerData
     * @return list<string>
     */
    public function handle(
        string $ticketTypeId,
        int $quantity = 1,
        ?Model $agent = null,
        ?array $customerData = null,
    ): array {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        /** @var TicketType|null $ticketType */
        $ticketType = TicketType::query()->with('ticketable')->find($ticketTypeId);

        if (! $ticketType instanceof TicketType) {
            throw new InvalidArgumentException('The selected ticket type does not exist.');
        }

        if ($ticketType->status !== 'active') {
            throw new InvalidArgumentException(sprintf(
                'Ticket type "%s" is not available for sale.',
                $ticketType->name,
            ));
        }

        $scopeRef = $this->scopeRef($ticketType);

        if ($scopeRef === null) {
            throw new InvalidArgumentException('The selected ticket type does not belong to an event scope.');
        }

        $scope = $this->scopeResolver->resolve($scopeRef['type'], $scopeRef['id']);
        $this->eligibility->ensureEligible($scope);
        $scopeData = $scope->toRegistrationData();

        return DB::transaction(function () use ($agent, $customerData, $quantity, $scope, $scopeData, $scopeRef, $ticketType): array {
            $this->registrationsApi->lockScope($scopeRef['type'], $scopeRef['id']);
            $this->enforceCapacity($quantity, $scope->capacity, $scope);

            $ids = [];

            for ($index = 0; $index < $quantity; $index++) {
                $ids[] = $this->registrations->register(array_merge($scopeData, [
                    'registrant_type' => $agent?->getMorphClass(),
                    'registrant_id' => $agent?->getKey(),
                    'registration_type' => 'individual',
                    'status' => 'confirmed',
                    'source' => 'agent_sale',
                    'total_participants' => 1,
                    'total_amount' => $ticketType->price,
                    'currency' => $ticketType->currency,
                    'items' => [[
                        'ticket_type_id' => $ticketType->getKey(),
                        'quantity' => 1,
                        'unit_price' => $ticketType->price,
                        'total_price' => $ticketType->price,
                        'currency' => $ticketType->currency,
                        'status' => 'confirmed',
                    ]],
                    'participants' => [$this->buildParticipantData($customerData, $index)],
                ]));
            }

            $issuedRegistrations = $ids;

            foreach ($ids as $registrationId) {
                $issuedRegistrations = array_values(array_unique(array_merge(
                    $issuedRegistrations,
                    $this->registrationsApi->expandComponents($registrationId),
                )));
            }

            $this->ticketing->shipInventory(
                ticketTypeId: (string) $ticketType->getKey(),
                quantity: $quantity,
                reason: 'agent_sale',
                reference: 'agent:' . ($agent?->getKey() ?? 'unknown'),
            );

            foreach ($issuedRegistrations as $registrationId) {
                $this->passes->issueForRegistration($registrationId);
            }

            return $ids;
        });
    }

    /**
     * @param  array<string, mixed>|null  $customerData
     * @return array<string, mixed>
     */
    private function buildParticipantData(?array $customerData, int $index): array
    {
        if ($customerData === null) {
            return [
                'name' => sprintf('Attendee #%d', $index + 1),
                'is_primary' => $index === 0,
            ];
        }

        return [
            'name' => $customerData['name'] ?? sprintf('Attendee #%d', $index + 1),
            'email' => $customerData['email'] ?? null,
            'phone' => $customerData['phone'] ?? null,
            'is_primary' => $index === 0,
        ];
    }

    private function enforceCapacity(int $quantity, ?int $capacity, EventRegistrationScopeData $scope): void
    {
        if (! config('events.features.enforce_scope_capacity_on_paid_registrations', false)) {
            return;
        }

        $remaining = $this->registrationsApi->capacityRemaining(
            eventId: $scope->eventId,
            occurrenceId: $scope->occurrenceId,
            sessionId: $scope->sessionId,
            capacity: $capacity,
        );

        if ($remaining === null) {
            return;
        }

        if ($quantity > $remaining) {
            throw new EventCapacityExceededException(
                sprintf(
                    'The event scope does not have enough capacity for %d agent sale registrations. Only %d remaining.',
                    $quantity,
                    $remaining,
                ),
            );
        }
    }

    /**
     * @return array{type: string, id: string}|null
     */
    private function scopeRef(TicketType $ticketType): ?array
    {
        $session = EventTicketScope::session($ticketType);

        if ($session !== null) {
            return ['type' => 'session', 'id' => (string) $session->getKey()];
        }

        $occurrence = EventTicketScope::occurrence($ticketType);

        if ($occurrence !== null) {
            return ['type' => 'occurrence', 'id' => (string) $occurrence->getKey()];
        }

        $event = EventTicketScope::event($ticketType);

        return $event !== null ? ['type' => 'event', 'id' => (string) $event->getKey()] : null;
    }
}
