<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Support\ModelResolver;

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
        $eventClass = ModelResolver::eventClass();

        if (method_exists($eventClass, 'ownerScopeConfig') && ! $eventClass::ownerScopeConfig()->enabled) {
            return $eventClass::query()->findOrFail($event->id);
        }

        return OwnerWriteGuard::findOrFailForOwner($eventClass, $event->id);
    }
}
