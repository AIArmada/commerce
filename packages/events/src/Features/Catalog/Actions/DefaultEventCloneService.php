<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Contracts\EventCloneService;
use AIArmada\Events\Contracts\EventScopeCloner;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Platform\ExtensionRegistry;
use Illuminate\Support\Facades\DB;

final class DefaultEventCloneService implements EventCloneService
{
    public function __construct(
        private readonly CloneEventAction $cloneEvent,
        private readonly CloneEventOccurrenceAction $cloneOccurrence,
        private readonly CloneEventSessionAction $cloneSession,
        private readonly ExtensionRegistry $extensions,
    ) {}

    public function cloneEvent(string $eventId, array $options = []): string
    {
        return DB::transaction(function () use ($eventId, $options): string {
            $clone = $this->cloneEvent->handle(Event::query()->whereKey($eventId)->firstOrFail(), $options);

            $this->cloneCrossFeatureScope('event', $eventId, 'event', (string) $clone->getKey(), $options);

            return (string) $clone->getKey();
        });
    }

    public function cloneOccurrence(string $occurrenceId, array $options = []): string
    {
        return DB::transaction(function () use ($occurrenceId, $options): string {
            $clone = $this->cloneOccurrence->handle(
                EventOccurrence::query()->whereKey($occurrenceId)->firstOrFail(),
                $options,
            );

            $this->cloneCrossFeatureScope('occurrence', $occurrenceId, 'occurrence', (string) $clone->getKey(), [
                ...$options,
                'target_event_id' => (string) $clone->event_id,
            ]);

            return (string) $clone->getKey();
        });
    }

    public function cloneSession(string $sessionId, array $options = []): string
    {
        return DB::transaction(function () use ($sessionId, $options): string {
            $clone = $this->cloneSession->handle(
                EventSession::query()->whereKey($sessionId)->firstOrFail(),
                $options,
            );

            $this->cloneCrossFeatureScope('session', $sessionId, 'session', (string) $clone->getKey(), [
                ...$options,
                'target_event_id' => (string) $clone->event_id,
                'target_occurrence_id' => $clone->event_occurrence_id,
            ]);

            return (string) $clone->getKey();
        });
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function cloneCrossFeatureScope(
        string $sourceScopeType,
        string $sourceScopeId,
        string $targetScopeType,
        string $targetScopeId,
        array $options = [],
    ): void {
        foreach ($this->extensions->resolve('events.clone-scope', EventScopeCloner::class) as $cloner) {
            $cloner->cloneForScope($sourceScopeType, $sourceScopeId, $targetScopeType, $targetScopeId, $options);
        }
    }
}
