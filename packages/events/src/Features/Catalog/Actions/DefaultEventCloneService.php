<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Contracts\EventCloneService;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
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
            $occurrence = ModelResolver::occurrenceClass()::query()->whereKey($occurrenceId)->firstOrFail();

            if (! $occurrence instanceof EventOccurrence) {
                throw new InvalidArgumentException('The configured occurrence model must extend EventOccurrence.');
            }

            $clone = $this->cloneOccurrence->handle($occurrence, $options);

            return (string) $clone->getKey();
        });
    }

    public function cloneSession(string $sessionId, array $options = []): string
    {
        return DB::transaction(function () use ($sessionId, $options): string {
            $session = ModelResolver::sessionClass()::query()->whereKey($sessionId)->firstOrFail();

            if (! $session instanceof EventSession) {
                throw new InvalidArgumentException('The configured session model must extend EventSession.');
            }

            $clone = $this->cloneSession->handle($session, $options);

            return (string) $clone->getKey();
        });
    }
}
