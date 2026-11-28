<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Platform\EventTicketScope;
use AIArmada\Ticketing\Features\TicketTypes\Actions\ExpandTicketTypeComponentsAction;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
        return DB::transaction(function () use ($parentRegistrationId, $multiplier, $options): array {
            /** @var EventRegistration $parentRegistration */
            $parentRegistration = EventRegistration::query()
                ->whereKey($parentRegistrationId)
                ->lockForUpdate()
                ->firstOrFail();

            $parentRegistration->loadMissing([
                'items.ticketType',
                'participants.contactMethods',
                'participants.answers',
                'childRegistrations.items',
            ]);

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

            $status = $this->registrationStatus($options, $parentRegistration);
            $source = $this->source($options, $parentRegistration);
            $paymentStatus = $this->paymentStatus($options, $parentRegistration);
            $metadata = $this->metadata($options, $parentRegistration);
            $participants = $this->participantPayloads($parentRegistration);
            $existingChildren = $this->existingChildrenByTicketType($parentRegistration);

            $childIds = [];

            foreach ($ticketType->components as $component) {
                $componentQuantity = $component->quantity * $multiplier;
                $componentTicketType = $component->getRelation('componentTicketType');

                if (! $componentTicketType instanceof TicketType) {
                    continue;
                }

                $scopeRef = $this->scopeRef($componentTicketType);

                if ($scopeRef === null) {
                    throw new InvalidArgumentException('Each component ticket type must belong to an event scope.');
                }

                $componentScope = $this->scopeResolver->resolve($scopeRef['type'], $scopeRef['id']);

                if ($componentScope->eventId !== (string) $parentRegistration->event_id) {
                    throw new InvalidArgumentException('Component ticket types must belong to the same event as the parent registration.');
                }

                $scopeData = $componentScope->toRegistrationData();

                for ($index = 0; $index < $componentQuantity; $index++) {
                    $existingId = $existingChildren[(string) $componentTicketType->getKey()][$index] ?? null;

                    if ($existingId !== null) {
                        $childIds[] = $existingId;

                        continue;
                    }

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
                }
            }

            if ($childIds !== []) {
                $parentRegistration->update([
                    'is_bundle_root' => true,
                    'pass_entitlements' => $this->entitlements($parentRegistration, $childIds),
                ]);
            }

            return array_values(array_unique($childIds));
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function participantPayloads(EventRegistration $parentRegistration): array
    {
        return $parentRegistration->participants
            ->map(function ($participant): array {
                $payload = Arr::only($participant->toArray(), [
                    'name',
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
                ]);

                $email = $participant->resolveEmail();
                $phone = $participant->resolvePhone();

                if ($email !== null) {
                    $payload['email'] = $email;
                }

                if ($phone !== null) {
                    $payload['phone'] = $phone;
                }

                $answers = $participant->getRelationValue('answers');

                if ($answers instanceof Collection && $answers->isNotEmpty()) {
                    $payload['answers'] = $answers
                        ->map(static fn ($answer): array => Arr::only($answer->toArray(), [
                            'field_key',
                            'question',
                            'answer',
                            'answer_json',
                            'metadata',
                        ]))
                        ->values()
                        ->all();
                }

                return $payload;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, list<string>>
     */
    private function existingChildrenByTicketType(EventRegistration $parentRegistration): array
    {
        $children = [];

        foreach ($parentRegistration->childRegistrations
            ->sortBy([['created_at', 'asc'], ['id', 'asc']]) as $child) {
            $item = $child->items->first();

            if ($item?->ticket_type_id === null) {
                continue;
            }

            $children[(string) $item->ticket_type_id][] = (string) $child->getKey();
        }

        return $children;
    }

    /**
     * @param  list<string>  $childIds
     * @return list<array<string, mixed>>
     */
    private function entitlements(EventRegistration $parentRegistration, array $childIds): array
    {
        $entitlements = [];
        $children = $parentRegistration->childRegistrations()
            ->with('items')
            ->whereIn('id', $childIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($children as $child) {
            $ticketTypeId = $child->items->first()?->ticket_type_id;

            if ($ticketTypeId === null) {
                continue;
            }

            $entitlements[] = [
                'event_registration_id' => (string) $child->getKey(),
                'ticket_type_id' => (string) $ticketTypeId,
                'event_occurrence_id' => $child->event_occurrence_id,
                'event_session_id' => $child->event_session_id,
                'event_id' => (string) $child->event_id,
            ];
        }

        return $entitlements;
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
