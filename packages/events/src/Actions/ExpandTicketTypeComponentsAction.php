<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Support\EventTicketScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

final class ExpandTicketTypeComponentsAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrations,
        private readonly EventRegistrationScopeResolver $scopeResolver,
    ) {}

    /**
     * @return Collection<int, EventRegistration>
     */
    public function handle(EventRegistration $parentRegistration, int $multiplier = 1): Collection
    {
        $ticketType = $parentRegistration->items()->first()?->ticketType;

        if ($ticketType === null) {
            return new Collection;
        }

        $ticketType->loadMissing('components.componentTicketType');

        if ($ticketType->components->isEmpty()) {
            return new Collection;
        }

        $ticketType->loadMissing('ticketable', 'components.componentTicketType');

        $target = EventTicketScope::target($ticketType);

        if ($target === null) {
            return new Collection;
        }

        $scope = $this->scopeResolver->resolve($target);

        $children = new Collection;
        $entitlements = $parentRegistration->getPassEntitlements();
        $scopeData = $scope->toRegistrationData();
        $event = $scope->event;

        foreach ($ticketType->components as $component) {
            $componentQuantity = $component->quantity * $multiplier;

            $componentTicketType = $component->getRelation('componentTicketType');

            if ($componentTicketType === null) {
                continue;
            }

            for ($i = 0; $i < $componentQuantity; $i++) {
                $child = $this->registrations->register(array_merge($scopeData, [
                    'registrant_type' => $parentRegistration->registrant_type,
                    'registrant_id' => $parentRegistration->registrant_id,
                    'registration_type' => 'component',
                    'status' => 'confirmed',
                    'source' => 'order',
                    'total_participants' => $parentRegistration->total_participants,
                    'total_amount' => 0,
                    'parent_registration_id' => $parentRegistration->getKey(),
                    'is_bundle_root' => false,
                    'items' => [[
                        'ticket_type_id' => $componentTicketType->getKey(),
                        'quantity' => 1,
                        'unit_price' => 0,
                        'total_price' => 0,
                        'currency' => $componentTicketType->currency,
                        'status' => 'confirmed',
                    ]],
                    'participants' => $parentRegistration->participants->map(
                        fn ($p) => Arr::only($p->toArray(), ['name', 'email', 'phone']),
                    )->toArray(),
                ]));

                $children->push($child);

                $entitlements[] = [
                    'event_registration_id' => $child->getKey(),
                    'ticket_type_id' => $componentTicketType->getKey(),
                    'event_occurrence_id' => $scope->occurrence?->getKey(),
                    'event_session_id' => $scope->session?->getKey(),
                    'event_id' => $event->getKey(),
                ];
            }
        }

        $parentRegistration->update([
            'is_bundle_root' => $parentRegistration->getPassEntitlements() === [],
            'pass_entitlements' => $entitlements,
        ]);

        return $children;
    }
}
