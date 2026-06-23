<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Support\EventRegistrationScope;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class RecordAgentTicketSaleAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrations,
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly ExpandTicketTypeComponentsAction $expandComponents,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @param  array<string, mixed>|null  $customerData
     * @return Collection<int, EventRegistration>
     */
    public function handle(
        EventTicketType $ticketType,
        int $quantity = 1,
        ?Model $agent = null,
        ?array $customerData = null,
    ): Collection {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        if ($ticketType->status !== 'active') {
            throw new InvalidArgumentException(sprintf(
                'Ticket type "%s" is not available for sale.',
                $ticketType->name,
            ));
        }

        $ticketType->loadMissing('event', 'occurrence', 'session');

        $target = $ticketType->session ?? $ticketType->occurrence ?? $ticketType->event;
        $scope = $this->scopeResolver->resolve($target);
        $scopeData = $scope->toRegistrationData();

        $this->enforceCapacity($quantity, $scope);

        $this->inventory->shipFromDefault($ticketType, $quantity, 'agent_sale', 'agent:' . ($agent?->getKey() ?? 'unknown'));

        $registrations = new Collection;

        for ($i = 0; $i < $quantity; $i++) {
            $participants = $this->buildParticipantData($customerData, $i);

            $registration = $this->registrations->register(array_merge($scopeData, [
                'registrant_type' => $agent?->getMorphClass(),
                'registrant_id' => $agent?->getKey(),
                'registration_type' => 'individual',
                'status' => 'confirmed',
                'source' => 'agent_sale',
                'total_participants' => 1,
                'total_amount' => $ticketType->price,
                'currency' => $ticketType->currency,
                'is_bundle_root' => true,
                'items' => [[
                    'event_ticket_type_id' => $ticketType->getKey(),
                    'quantity' => 1,
                    'unit_price' => $ticketType->price,
                    'total_price' => $ticketType->price,
                    'currency' => $ticketType->currency,
                    'status' => 'confirmed',
                ]],
                'participants' => [$participants],
            ]));

            $this->expandComponents->handle($registration);

            $registrations->push($registration);
        }

        return $registrations;
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

    private function enforceCapacity(int $quantity, EventRegistrationScope $scope): void
    {
        if (! config('events.features.enforce_scope_capacity_on_paid_registrations', false)) {
            return;
        }

        $remaining = $scope->capacityRemaining();

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
}
