<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Support\EventTicketScope;
use AIArmada\Ticketing\Actions\ExpandTicketTypeComponentsAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

/**
 * Creates event registrations for ticketing components.
 *
 * Ticketing owns component discovery; events owns the event-registration
 * projection because ticketing cannot depend on this package's event models.
 */
final class CreateEventComponentRegistrationsAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrations,
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly ExpandTicketTypeComponentsAction $components,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return Collection<int, EventRegistration>
     */
    public function handle(EventRegistration $parentRegistration, int $multiplier = 1, array $options = []): Collection
    {
        $ticketType = $parentRegistration->items()->first()?->ticketType;

        if ($ticketType === null) {
            return new Collection;
        }

        $ticketType->loadMissing('components.componentTicketType');

        if ($ticketType->components->isEmpty()) {
            return new Collection;
        }

        $this->components->handle($ticketType, $multiplier);
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
        $status = $this->registrationStatus($options, $parentRegistration);
        $source = $this->source($options, $parentRegistration);
        $paymentStatus = $this->paymentStatus($options, $parentRegistration);
        $metadata = $this->metadata($options, $parentRegistration);

        foreach ($ticketType->components as $component) {
            $componentQuantity = $component->quantity * $multiplier;
            $componentTicketType = $component->getRelation('componentTicketType');

            if ($componentTicketType === null) {
                continue;
            }

            for ($index = 0; $index < $componentQuantity; $index++) {
                $child = $this->registrations->register(array_merge($scopeData, [
                    'registrant_type' => $parentRegistration->registrant_type,
                    'registrant_id' => $parentRegistration->registrant_id,
                    'registration_type' => 'component',
                    'status' => $status,
                    'source' => $source,
                    'total_participants' => $parentRegistration->total_participants,
                    'total_amount' => 0,
                    'payment_status' => $paymentStatus,
                    'metadata' => $metadata,
                    'parent_registration_id' => $parentRegistration->getKey(),
                    'is_bundle_root' => false,
                    'items' => [[
                        'ticket_type_id' => $componentTicketType->getKey(),
                        'quantity' => 1,
                        'unit_price' => 0,
                        'total_price' => 0,
                        'currency' => $componentTicketType->currency,
                        'status' => $status,
                    ]],
                    'participants' => $parentRegistration->participants->map(
                        fn ($participant) => Arr::only($participant->toArray(), [
                            'name',
                            'email',
                            'phone',
                            'participant_type',
                            'participant_id',
                            'relationship_to_registrant',
                            'is_primary',
                            'is_purchaser',
                            'age',
                            'gender',
                            'status',
                            'notes',
                            'metadata',
                            'answers',
                        ]),
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

    private function registrationStatus(array $options, EventRegistration $parentRegistration): string
    {
        $status = $options['status'] ?? $parentRegistration->status->getValue();

        return is_string($status) && in_array($status, ['pending', 'confirmed'], true)
            ? $status
            : 'confirmed';
    }

    private function source(array $options, EventRegistration $parentRegistration): string
    {
        $source = $options['source'] ?? $parentRegistration->source;

        return is_string($source) && mb_trim($source) !== '' ? mb_trim($source) : 'order';
    }

    private function paymentStatus(array $options, EventRegistration $parentRegistration): ?string
    {
        $paymentStatus = $options['payment_status'] ?? $parentRegistration->payment_status;

        return is_string($paymentStatus) && mb_trim($paymentStatus) !== '' ? mb_trim($paymentStatus) : null;
    }

    /** @return array<string, mixed>|null */
    private function metadata(array $options, EventRegistration $parentRegistration): ?array
    {
        $metadata = $options['metadata'] ?? $parentRegistration->metadata;

        return is_array($metadata) ? $metadata : null;
    }
}
