<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Contracts\EventCloneService;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Platform\EventWriteGuard;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DefaultEventCloneService implements EventCloneService
{
    public function __construct(
        private readonly CloneEventAction $cloneEvent,
        private readonly CloneEventOccurrenceAction $cloneOccurrence,
        private readonly CloneEventSessionAction $cloneSession,
    ) {}

    public function cloneEvent(string $eventId, array $options = []): string
    {
        return DB::transaction(function () use ($eventId, $options): string {
            $event = EventWriteGuard::findOrFail($eventId);

            if (! $event instanceof Event) {
                throw new InvalidArgumentException('The configured events model must extend the Catalog Event model.');
            }

            $clone = $this->cloneEvent->handle($event, $options);

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

            return (string) $clone->getKey();
        });
    }
}
