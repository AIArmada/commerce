<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Queries;

use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\Events\Features\Catalog\Actions\CreateEventFromSubmissionAction;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Catalog\Api\EventCreationData;
use AIArmada\Events\Features\Catalog\Api\EventOwnerRef;
use AIArmada\Events\Features\Catalog\Api\EventScopeData;
use AIArmada\Events\Features\Catalog\Api\EventScopeRef;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\Gate;

final class EloquentCatalogApi implements CatalogApi
{
    public function __construct(
        private readonly CreateEventFromSubmissionAction $createEventFromSubmission,
    ) {}

    public function resolveScope(string $scopeType, string $scopeId): ?EventScopeRef
    {
        if ($scopeType === 'event') {
            return $this->eventExists($scopeId)
                ? new EventScopeRef(eventId: $scopeId)
                : null;
        }

        if ($scopeType === 'occurrence') {
            $occurrenceClass = ModelResolver::occurrenceClass();
            $occurrence = $occurrenceClass::query()->whereKey($scopeId)->first();

            if (! $occurrence instanceof EventOccurrence) {
                return null;
            }

            return new EventScopeRef(
                eventId: (string) $occurrence->event_id,
                occurrenceId: $scopeId,
            );
        }

        if ($scopeType === 'session') {
            $sessionClass = ModelResolver::sessionClass();
            $session = $sessionClass::query()->whereKey($scopeId)->first();

            if (! $session instanceof EventSession) {
                return null;
            }

            return new EventScopeRef(
                eventId: (string) $session->event_id,
                occurrenceId: $session->event_occurrence_id !== null ? (string) $session->event_occurrence_id : null,
                sessionId: $scopeId,
            );
        }

        return null;
    }

    public function describeScope(string $scopeType, string $scopeId): ?EventScopeData
    {
        return match ($scopeType) {
            'event' => $this->describeEvent($scopeId),
            'occurrence' => $this->describeOccurrence($scopeId),
            'session' => $this->describeSession($scopeId),
            default => null,
        };
    }

    public function eventExists(string $eventId): bool
    {
        $eventClass = ModelResolver::eventClass();

        return $eventClass::query()->whereKey($eventId)->exists();
    }

    public function occurrenceBelongsToEvent(string $occurrenceId, string $eventId): bool
    {
        $occurrenceClass = ModelResolver::occurrenceClass();

        return $occurrenceClass::query()
            ->whereKey($occurrenceId)
            ->where('event_id', $eventId)
            ->exists();
    }

    public function sessionBelongsToOccurrence(string $sessionId, string $occurrenceId): bool
    {
        $sessionClass = ModelResolver::sessionClass();

        return $sessionClass::query()
            ->whereKey($sessionId)
            ->where('event_occurrence_id', $occurrenceId)
            ->exists();
    }

    public function eventMetadata(string $eventId): array
    {
        $eventClass = ModelResolver::eventClass();
        $event = $eventClass::query()->whereKey($eventId)->first();

        if (! $event instanceof Event) {
            return [];
        }

        $metadata = $event->metadata;

        return is_array($metadata) ? $metadata : [];
    }

    public function eventOwnerRef(string $eventId): ?EventOwnerRef
    {
        $eventClass = ModelResolver::eventClass();
        $event = $eventClass::query()->whereKey($eventId)->first();

        if (! $event instanceof Event) {
            return null;
        }

        $ownerType = $event->getAttribute('owner_type');
        $ownerId = $event->getAttribute('owner_id');

        if (! is_string($ownerType) || $ownerType === '' || $ownerId === null || $ownerId === '') {
            return null;
        }

        return new EventOwnerRef(type: $ownerType, id: (string) $ownerId);
    }

    public function lockEvent(string $eventId): void
    {
        $eventClass = ModelResolver::eventClass();
        $eventClass::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
    }

    public function authorizeManageAdmissions(mixed $actor, string $eventId): void
    {
        $eventClass = ModelResolver::eventClass();
        $event = $eventClass::query()->whereKey($eventId)->firstOrFail();

        Gate::forUser($actor)->authorize('manageAdmissions', $event);
    }

    public function createEventFromSubmission(EventCreationData $data): string
    {
        return $this->createEventFromSubmission->handle($data);
    }

    private function describeEvent(string $eventId): ?EventScopeData
    {
        $eventClass = ModelResolver::eventClass();
        $event = $eventClass::query()->withoutGlobalScope(OwnerScope::class)->whereKey($eventId)->first();

        if (! $event instanceof Event) {
            return null;
        }

        return new EventScopeData(
            scopeType: 'event',
            scopeId: (string) $event->getKey(),
            eventId: (string) $event->getKey(),
            occurrenceId: null,
            sessionId: null,
            morphClass: $event->getMorphClass(),
            title: $event->title,
            status: $event->status->getValue(),
            capacity: null,
            startsAt: $this->toImmutable($event->getAttribute('starts_at')),
            pricingMode: $event->effectivePricingMode()->value,
            registrationMode: $event->effectiveRegistrationMode()->value,
            shouldIssuePassesForFree: $event->shouldIssuePassesForFree(),
        );
    }

    private function describeOccurrence(string $occurrenceId): ?EventScopeData
    {
        $occurrenceClass = ModelResolver::occurrenceClass();
        $occurrence = $occurrenceClass::query()->withoutGlobalScope(EventWriteGuard::OWNER_SCOPE)->whereKey($occurrenceId)->first();

        if (! $occurrence instanceof EventOccurrence) {
            return null;
        }

        return new EventScopeData(
            scopeType: 'occurrence',
            scopeId: (string) $occurrence->getKey(),
            eventId: (string) $occurrence->event_id,
            occurrenceId: (string) $occurrence->getKey(),
            sessionId: null,
            morphClass: $occurrence->getMorphClass(),
            title: $occurrence->title,
            status: $occurrence->status->getValue(),
            capacity: $occurrence->capacity,
            startsAt: $this->toImmutable($occurrence->starts_at),
            pricingMode: $occurrence->effectivePricingMode()->value,
            registrationMode: $occurrence->effectiveRegistrationMode()->value,
            shouldIssuePassesForFree: $occurrence->shouldIssuePassesForFree(),
        );
    }

    private function describeSession(string $sessionId): ?EventScopeData
    {
        $sessionClass = ModelResolver::sessionClass();
        $session = $sessionClass::query()->withoutGlobalScope(EventWriteGuard::OWNER_SCOPE)->whereKey($sessionId)->first();

        if (! $session instanceof EventSession) {
            return null;
        }

        return new EventScopeData(
            scopeType: 'session',
            scopeId: (string) $session->getKey(),
            eventId: (string) $session->event_id,
            occurrenceId: $session->event_occurrence_id !== null ? (string) $session->event_occurrence_id : null,
            sessionId: (string) $session->getKey(),
            morphClass: $session->getMorphClass(),
            title: $session->title,
            status: $session->status->getValue(),
            capacity: $session->capacity,
            startsAt: $this->toImmutable($session->starts_at),
            pricingMode: $session->effectivePricingMode()->value,
            registrationMode: $session->effectiveRegistrationMode()->value,
            shouldIssuePassesForFree: $session->shouldIssuePassesForFree(),
        );
    }

    private function toImmutable(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return null;
    }
}
