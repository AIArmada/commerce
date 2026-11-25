<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Platform\EventTicketScope;
use AIArmada\Ticketing\Actions\ExpandTicketTypeComponentsAction;
use AIArmada\Ticketing\Models\TicketType;
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
     * @return list<string>
     */
    public function handle(string $parentRegistrationId, int $multiplier = 1, array $options = []): array
    {
        /** @var EventRegistration $parentRegistration */
        $parentRegistration = EventRegistration::query()->findOrFail($parentRegistrationId);

        $parentRegistration->loadMissing(['items.ticketType', 'participants']);

        $ticketType = $parentRegistration->items->first()?->ticketType;

        if (! $ticketType instanceof TicketType) {
            return [];
        }

        $ticketType->loadMissing('components.componentTicketType');

        if ($ticketType->components->isEmpty()) {
            return [];
        }

        $this->components->handle($ticketType, $multiplier);
        $ticketType->loadMissing('ticketable', 'components.componentTicketType');

        $scopeRef = $this->scopeRef($ticketType);

        if ($scopeRef === null) {
            return [];
        }

        $scope = $this->scopeResolver->resolve($scopeRef['type'], $scopeRef['id']);
        $scopeData = $scope->toRegistrationData();

        $status = $this->registrationStatus($options, $parentRegistration);
        $source = $this->source($options, $parentRegistration);
        $paymentStatus = $this->paymentStatus($options, $parentRegistration);
        $metadata = $this->metadata($options, $parentRegistration);
        $participants = $parentRegistration->participants
            ->map(fn ($participant): array => Arr::only($participant->toArray(), [
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
            ]))
            ->all();

        $entitlements = $parentRegistration->getPassEntitlements();
        $childIds = [];

        foreach ($ticketType->components as $component) {
            $componentQuantity = $component->quantity * $multiplier;
            $componentTicketType = $component->getRelation('componentTicketType');

            if (! $componentTicketType instanceof TicketType) {
                continue;
            }

            for ($index = 0; $index < $componentQuantity; $index++) {
                $childId = $this->registrations->register(array_merge($scopeData, [
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
                    'participants' => $participants,
                ]));

                $childIds[] = $childId;

                $entitlements[] = [
                    'event_registration_id' => $childId,
                    'ticket_type_id' => $componentTicketType->getKey(),
                    'event_occurrence_id' => $scope->occurrenceId,
                    'event_session_id' => $scope->sessionId,
                    'event_id' => $scope->eventId,
                ];
            }
        }

        if ($childIds !== []) {
            $parentRegistration->update([
                'is_bundle_root' => $parentRegistration->getPassEntitlements() === [],
                'pass_entitlements' => $entitlements,
            ]);
        }

        return $childIds;
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

    /**
     * @param  array<string, mixed>  $options
     */
    private function registrationStatus(array $options, EventRegistration $parentRegistration): string
    {
        $status = $options['status'] ?? $parentRegistration->status->getValue();

        return is_string($status) && in_array($status, ['pending', 'confirmed'], true)
            ? $status
            : 'confirmed';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function source(array $options, EventRegistration $parentRegistration): string
    {
        $source = $options['source'] ?? $parentRegistration->source;

        return is_string($source) && mb_trim($source) !== '' ? mb_trim($source) : 'order';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function paymentStatus(array $options, EventRegistration $parentRegistration): ?string
    {
        $paymentStatus = $options['payment_status'] ?? $parentRegistration->payment_status;

        return is_string($paymentStatus) && mb_trim($paymentStatus) !== '' ? mb_trim($paymentStatus) : null;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null
     */
    private function metadata(array $options, EventRegistration $parentRegistration): ?array
    {
        $metadata = $options['metadata'] ?? $parentRegistration->metadata;

        return is_array($metadata) ? $metadata : null;
    }
}
