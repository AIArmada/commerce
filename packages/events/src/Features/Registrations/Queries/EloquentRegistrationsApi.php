<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Queries;

use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Events\Features\Registrations\Actions\CreateEventComponentRegistrationsAction;
use AIArmada\Events\Features\Registrations\Actions\RecordRegistrationPaymentStatusAction;
use AIArmada\Events\Features\Registrations\Actions\RegisterForFreeAction;
use AIArmada\Events\Features\Registrations\Api\EventRegistrationOrderItemRef;
use AIArmada\Events\Features\Registrations\Api\EventRegistrationParticipantData;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Features\Registrations\Api\RegistrationSummary;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\Models\EventRegistrationItem;
use AIArmada\Events\Features\Registrations\Models\EventRegistrationParticipant;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class EloquentRegistrationsApi implements RegistrationsApi
{
    public function __construct(
        private readonly RegisterForFreeAction $registerForFree,
        private readonly CreateEventComponentRegistrationsAction $expandComponents,
        private readonly RecordRegistrationPaymentStatusAction $paymentStatus,
        private readonly RegistrationCapacityQuery $capacity,
    ) {}

    public function registrationMorphClass(): string
    {
        $registrationClass = ModelResolver::registrationClass();

        return (new $registrationClass)->getMorphClass();
    }

    public function summarize(string $registrationId): ?RegistrationSummary
    {
        $registrationClass = ModelResolver::registrationClass();
        $registration = $registrationClass::query()->whereKey($registrationId)->first();

        return $registration instanceof EventRegistration ? $this->summary($registration) : null;
    }

    public function forOrder(string $orderId, string $orderType, ?array $registrationIds = null): array
    {
        $registrationClass = ModelResolver::registrationClass();
        $registrations = $registrationClass::query()
            ->where('external_order_id', $orderId)
            ->where('external_order_type', $orderType)
            ->when($registrationIds !== null, fn (Builder $query): Builder => $query->whereIn('id', $registrationIds))
            ->get();

        return $registrations
            ->filter(static fn (Model $registration): bool => $registration instanceof EventRegistration)
            ->map(fn (EventRegistration $registration): RegistrationSummary => $this->summary($registration))
            ->values()
            ->all();
    }

    public function registrationIdsForOrderItem(
        string $eventId,
        ?string $occurrenceId,
        ?string $sessionId,
        string $orderItemId,
        string $orderItemType,
    ): array {
        $registrationClass = ModelResolver::registrationClass();
        $query = $registrationClass::query()->where('event_id', $eventId);

        $this->applyScopeColumns($query, $occurrenceId, $sessionId);

        return $query
            ->whereHas('items', function (Builder $items) use ($orderItemId, $orderItemType): void {
                $items
                    ->where('external_order_item_id', $orderItemId)
                    ->where('external_order_item_type', $orderItemType);
            })
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function registrationIdsWithOrderLink(string $scopeType, string $scopeId): array
    {
        $column = $this->scopeColumn($scopeType);
        $registrationClass = ModelResolver::registrationClass();

        return $registrationClass::query()
            ->where($column, $scopeId)
            ->whereNotNull('external_order_id')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function registrationIdsForOrderWithTicketTypes(
        string $orderId,
        string $orderType,
        array $ticketTypeIds,
    ): array {
        if ($ticketTypeIds === []) {
            return [];
        }

        $registrationClass = ModelResolver::registrationClass();

        return $registrationClass::query()
            ->where('external_order_id', $orderId)
            ->where('external_order_type', $orderType)
            ->whereHas('items', function (Builder $items) use ($ticketTypeIds): void {
                $items->whereIn('ticket_type_id', $ticketTypeIds);
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function orderItemsForRegistration(string $registrationId): array
    {
        return EventRegistrationItem::query()
            ->where('event_registration_id', $registrationId)
            ->get()
            ->map(fn (EventRegistrationItem $item): EventRegistrationOrderItemRef => $this->orderItemRef($item))
            ->all();
    }

    public function findOrderItem(string $registrationItemId): ?EventRegistrationOrderItemRef
    {
        /** @var EventRegistrationItem|null $item */
        $item = EventRegistrationItem::query()->whereKey($registrationItemId)->first();

        return $item instanceof EventRegistrationItem ? $this->orderItemRef($item) : null;
    }

    public function participantsForRegistration(string $registrationId): array
    {
        $participantClass = ModelResolver::registrationParticipantClass();

        return $participantClass::query()
            ->where('event_registration_id', $registrationId)
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get()
            ->filter(static fn (Model $participant): bool => $participant instanceof EventRegistrationParticipant)
            ->map(fn (EventRegistrationParticipant $participant): EventRegistrationParticipantData => $this->participantData($participant))
            ->values()
            ->all();
    }

    public function findParticipant(string $participantId): ?EventRegistrationParticipantData
    {
        $participantClass = ModelResolver::registrationParticipantClass();
        $participant = $participantClass::query()->whereKey($participantId)->first();

        return $participant instanceof EventRegistrationParticipant ? $this->participantData($participant) : null;
    }

    public function passIdsForRegistration(string $registrationId): array
    {
        $registrationClass = ModelResolver::registrationClass();
        $registration = $registrationClass::query()->whereKey($registrationId)->first();

        if (! $registration instanceof EventRegistration) {
            return [];
        }

        return $registration->passes()
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function lock(string $registrationId): void
    {
        $registrationClass = ModelResolver::registrationClass();
        $registrationClass::query()
            ->withoutGlobalScopes([OwnerScope::class, EventWriteGuard::OWNER_SCOPE])
            ->whereKey($registrationId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function lockScope(string $scopeType, string $scopeId): void
    {
        $modelClass = $this->scopeModelClass($scopeType);
        $modelClass::query()
            ->whereKey($scopeId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function capacityRemaining(
        string $eventId,
        ?string $occurrenceId,
        ?string $sessionId,
        ?int $capacity,
    ): ?int {
        return $this->capacity->remaining($eventId, $occurrenceId, $sessionId, $capacity);
    }

    public function recordPaymentStatus(string $registrationId, string $paymentStatus): void
    {
        $this->paymentStatus->handle($registrationId, $paymentStatus);
    }

    public function registerForFree(
        string $scopeType,
        string $scopeId,
        array $participants,
        ?string $registrantType = null,
        ?string $registrantId = null,
        array $options = [],
    ): array {
        return $this->registerForFree->execute(
            scopeType: $scopeType,
            scopeId: $scopeId,
            participants: $participants,
            registrantType: $registrantType,
            registrantId: $registrantId,
            options: $options,
        );
    }

    public function expandComponents(string $parentRegistrationId, array $options = []): void
    {
        $this->expandComponents->handle($parentRegistrationId, options: $options);
    }

    public function authorizeRefundRequest(mixed $actor, string $registrationId): void
    {
        $registrationClass = ModelResolver::registrationClass();
        $registration = $registrationClass::query()->whereKey($registrationId)->firstOrFail();

        Gate::forUser($actor)->authorize('requestRefund', $registration);
    }

    private function summary(EventRegistration $registration): RegistrationSummary
    {
        return new RegistrationSummary(
            id: (string) $registration->getKey(),
            registrationNo: (string) $registration->registration_no,
            eventId: (string) $registration->event_id,
            occurrenceId: $registration->event_occurrence_id !== null ? (string) $registration->event_occurrence_id : null,
            sessionId: $registration->event_session_id !== null ? (string) $registration->event_session_id : null,
            status: (string) $registration->status->getValue(),
            registrantType: $registration->registrant_type !== null ? (string) $registration->registrant_type : null,
            registrantId: $registration->registrant_id !== null ? (string) $registration->registrant_id : null,
            totalAmount: (int) $registration->total_amount,
            currency: (string) $registration->currency,
            paymentStatus: $registration->payment_status !== null ? (string) $registration->payment_status : null,
            isBundleRoot: (bool) $registration->is_bundle_root,
            parentRegistrationId: $registration->parent_registration_id !== null ? (string) $registration->parent_registration_id : null,
            externalOrderId: $registration->external_order_id !== null ? (string) $registration->external_order_id : null,
            externalOrderType: $registration->external_order_type !== null ? (string) $registration->external_order_type : null,
        );
    }

    private function participantData(EventRegistrationParticipant $participant): EventRegistrationParticipantData
    {
        return new EventRegistrationParticipantData(
            id: (string) $participant->getKey(),
            registrationId: (string) $participant->event_registration_id,
            eventId: (string) $participant->event_id,
            occurrenceId: $participant->event_occurrence_id !== null ? (string) $participant->event_occurrence_id : null,
            sessionId: $participant->event_session_id !== null ? (string) $participant->event_session_id : null,
            morphClass: $participant->getMorphClass(),
            name: (string) $participant->name,
            email: $participant->resolveEmail(),
            phone: $participant->resolvePhone(),
            isPrimary: (bool) $participant->is_primary,
            participantType: $participant->participant_type !== null ? (string) $participant->participant_type : null,
            participantId: $participant->participant_id !== null ? (string) $participant->participant_id : null,
            relationshipToRegistrant: $participant->relationship_to_registrant !== null ? (string) $participant->relationship_to_registrant : null,
            age: $participant->age !== null ? (int) $participant->age : null,
            gender: $participant->gender !== null ? (string) $participant->gender : null,
            status: $participant->status !== null ? (string) $participant->status : null,
            notes: $participant->notes !== null ? (string) $participant->notes : null,
            metadata: is_array($participant->metadata) ? $participant->metadata : null,
        );
    }

    private function orderItemRef(EventRegistrationItem $item): EventRegistrationOrderItemRef
    {
        return new EventRegistrationOrderItemRef(
            id: (string) $item->getKey(),
            registrationId: (string) $item->event_registration_id,
            eventId: (string) $item->event_id,
            ticketTypeId: $item->ticket_type_id !== null ? (string) $item->ticket_type_id : null,
            externalOrderItemId: $item->external_order_item_id !== null ? (string) $item->external_order_item_id : null,
            externalOrderItemType: $item->external_order_item_type !== null ? (string) $item->external_order_item_type : null,
            quantity: (int) $item->quantity,
        );
    }

    private function applyScopeColumns(Builder $query, ?string $occurrenceId, ?string $sessionId): void
    {
        if ($occurrenceId !== null) {
            $query->where('event_occurrence_id', $occurrenceId);
        } else {
            $query->whereNull('event_occurrence_id');
        }

        if ($sessionId !== null) {
            $query->where('event_session_id', $sessionId);
        } else {
            $query->whereNull('event_session_id');
        }
    }

    private function scopeColumn(string $scopeType): string
    {
        return match ($scopeType) {
            'event' => 'event_id',
            'occurrence' => 'event_occurrence_id',
            'session' => 'event_session_id',
            default => throw new InvalidArgumentException(sprintf('Unknown event scope type [%s].', $scopeType)),
        };
    }

    /**
     * @return class-string<Model>
     */
    private function scopeModelClass(string $scopeType): string
    {
        return match ($scopeType) {
            'event' => ModelResolver::eventClass(),
            'occurrence' => ModelResolver::occurrenceClass(),
            'session' => ModelResolver::sessionClass(),
            default => throw new InvalidArgumentException(sprintf('Unknown event scope type [%s].', $scopeType)),
        };
    }
}
