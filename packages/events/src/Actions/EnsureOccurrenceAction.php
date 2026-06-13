<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use Carbon\CarbonImmutable;

final class EnsureOccurrenceAction
{
    public function handle(Event $event, array $attributes = []): EventOccurrence
    {
        OwnerWriteGuard::findOrFailForOwner(Event::class, $event->id);

        $startsAt = $attributes['starts_at'] ?? null;

        $occurrence = $startsAt !== null
            ? $event->occurrences()->where('starts_at', $startsAt)->first()
            : $event->occurrences()->first();

        if (! $occurrence) {
            $occurrence = $event->occurrences()->create([
                'title' => $attributes['title'] ?? $event->title,
                'starts_at' => $startsAt ?? CarbonImmutable::now()->addDay(),
                'ends_at' => $attributes['ends_at'] ?? CarbonImmutable::now()->addDay()->addHours(2),
                'timezone' => $attributes['timezone'] ?? $event->timezone ?? 'UTC',
                'status' => $attributes['status'] ?? 'scheduled',
                'visibility' => $attributes['visibility'] ?? $event->visibility ?? 'public',
                'delivery_mode' => $attributes['delivery_mode'] ?? $event->delivery_mode,
                'capacity' => $attributes['capacity'] ?? null,
                'metadata' => $attributes['metadata'] ?? null,
            ]);
        }

        return $occurrence;
    }
}
