<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;

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
        if (method_exists(Event::class, 'ownerScopeConfig') && ! Event::ownerScopeConfig()->enabled) {
            return Event::query()->findOrFail($event->id);
        }

        return OwnerWriteGuard::findOrFailForOwner(Event::class, $event->id);
    }
}
