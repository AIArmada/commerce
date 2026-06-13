<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Events\EventSessionCreated;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateEventSessionAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(EventOccurrence $occurrence, array $attributes = []): EventSession
    {
        OwnerWriteGuard::findOrFailForOwner(Event::class, $occurrence->event_id);

        $title = $attributes['title'] ?? throw new InvalidArgumentException('Session title is required.');

        $startsAt = isset($attributes['starts_at'])
            ? CarbonImmutable::parse($attributes['starts_at'])
            : CarbonImmutable::now();

        $endsAt = isset($attributes['ends_at'])
            ? CarbonImmutable::parse($attributes['ends_at'])
            : $startsAt->addHour();

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException('Session end time must be after the start time.');
        }

        $slug = $attributes['slug'] ?? Str::slug($title);

        $sortOrder = $attributes['sort_order']
            ?? (int) EventSession::query()
                ->where('event_occurrence_id', $occurrence->id)
                ->max('sort_order') + 1;

        $session = EventSession::query()->create([
            'event_id' => $occurrence->event_id,
            'event_occurrence_id' => $occurrence->id,
            'title' => $title,
            'slug' => $slug,
            'summary' => $attributes['summary'] ?? null,
            'description' => $attributes['description'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => $attributes['timezone'] ?? $occurrence->timezone ?? 'UTC',
            'status' => $attributes['status'] ?? 'scheduled',
            'visibility' => $attributes['visibility'] ?? 'public',
            'delivery_mode' => $attributes['delivery_mode'] ?? $occurrence->delivery_mode ?? 'in_person',
            'capacity' => $attributes['capacity'] ?? null,
            'sort_order' => $sortOrder,
            'metadata' => $attributes['metadata'] ?? null,
        ]);

        event(new EventSessionCreated($session));

        return $session;
    }
}
