<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Events\EventOccurrenceCreated;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateEventOccurrenceAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Event $event, array $attributes = []): EventOccurrence
    {
        $event = $this->resolveEventForWrite($event);

        $title = blank($attributes['title'] ?? null)
            ? throw new InvalidArgumentException('Occurrence title is required.')
            : (string) $attributes['title'];

        $startsAt = blank($attributes['starts_at'] ?? null)
            ? CarbonImmutable::now()->addDay()
            : CarbonImmutable::parse($attributes['starts_at']);

        $endsAt = blank($attributes['ends_at'] ?? null)
            ? $startsAt->addHours(2)
            : CarbonImmutable::parse($attributes['ends_at']);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException('Occurrence end time must be after the start time.');
        }

        $slug = blank($attributes['slug'] ?? null)
            ? Str::slug($title)
            : (string) $attributes['slug'];

        $occurrence = EventOccurrence::query()->create([
            'event_id' => $event->id,
            'title' => $title,
            'slug' => $slug,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => blank($attributes['timezone'] ?? null)
                ? (blank($event->timezone ?? null) ? config('events.defaults.timezone', 'UTC') : $event->timezone)
                : (string) $attributes['timezone'],
            'status' => blank($attributes['status'] ?? null)
                ? EventOccurrence::SCHEDULED
                : (string) $attributes['status'],
            'visibility' => blank($attributes['visibility'] ?? null)
                ? (blank($event->visibility ?? null) ? Event::PUBLIC : $event->visibility)
                : (string) $attributes['visibility'],
            'delivery_mode' => blank($attributes['delivery_mode'] ?? null)
                ? (blank($event->delivery_mode ?? null) ? Event::DELIVERY_PHYSICAL : $event->delivery_mode)
                : (string) $attributes['delivery_mode'],
            'capacity' => blank($attributes['capacity'] ?? null)
                ? null
                : (int) $attributes['capacity'],
            'metadata' => $attributes['metadata'] ?? null,
        ]);

        event(new EventOccurrenceCreated($occurrence));

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
