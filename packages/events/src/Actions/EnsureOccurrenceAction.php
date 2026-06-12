<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use Carbon\CarbonImmutable;

final class EnsureOccurrenceAction
{
    public function handle(Event $event, array $attributes = []): EventOccurrence
    {
        $occurrence = $event->occurrences()->first();

        if (! $occurrence) {
            $occurrence = $event->occurrences()->create([
                'title' => $attributes['title'] ?? $event->title,
                'starts_at' => $attributes['starts_at'] ?? CarbonImmutable::now()->addDay(),
                'ends_at' => $attributes['ends_at'] ?? CarbonImmutable::now()->addDay()->addHours(2),
                'timezone' => $attributes['timezone'] ?? $event->timezone ?? 'UTC',
                'status' => 'scheduled',
                'visibility' => $event->visibility ?? 'public',
                'delivery_mode' => $event->delivery_mode,
            ]);
        }

        return $occurrence;
    }
}
