<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Platform\EventWriteGuard;
use InvalidArgumentException;

final class EnsureOccurrenceAction
{
    public function __construct(
        private readonly CreateEventOccurrenceAction $createOccurrence,
    ) {}

    public function handle(Event $event, array $attributes = []): EventOccurrence
    {
        $event = $this->resolveEventForWrite($event);

        $startsAt = $attributes['starts_at'] ?? null;

        $occurrence = $startsAt !== null
            ? $event->occurrences()->where('starts_at', $startsAt)->first()
            : $event->occurrences()->first();

        if (! $occurrence) {
            return $this->createOccurrence->handle($event, $attributes);
        }

        return $occurrence;
    }

    private function resolveEventForWrite(Event $event): Event
    {
        $resolved = EventWriteGuard::findOrFail($event->getKey());

        if (! $resolved instanceof Event) {
            throw new InvalidArgumentException('The configured events model must extend the Catalog Event model.');
        }

        return $resolved;
    }
}
