<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Api;

interface CatalogApi
{
    public function resolveScope(string $scopeType, string $scopeId): ?EventScopeRef;

    public function describeScope(string $scopeType, string $scopeId): ?EventScopeData;

    public function eventExists(string $eventId): bool;

    public function occurrenceBelongsToEvent(string $occurrenceId, string $eventId): bool;

    public function sessionBelongsToOccurrence(string $sessionId, string $occurrenceId): bool;

    /**
     * @return array<string, mixed>
     */
    public function eventMetadata(string $eventId): array;

    public function eventOwnerRef(string $eventId): ?EventOwnerRef;

    public function lockEvent(string $eventId): void;

    public function authorizeManageAdmissions(mixed $actor, string $eventId): void;

    public function createEventFromSubmission(EventCreationData $data): string;
}
