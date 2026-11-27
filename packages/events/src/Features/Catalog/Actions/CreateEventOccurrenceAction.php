<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Events\EventOccurrenceCreated;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Platform\EventContentNormalizer;
use AIArmada\Events\Platform\EventWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateEventOccurrenceAction
{
    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Event $event, array $attributes = []): EventOccurrence
    {
        $event = $this->resolveEventForWrite($event);

        $title = blank($attributes['title'] ?? null)
            ? throw new InvalidArgumentException('Occurrence title is required.')
            : $this->contentNormalizer->normalizeTitle((string) $attributes['title']);

        $startsAt = blank($attributes['starts_at'] ?? null)
            ? CarbonImmutable::now()->addDay()
            : CarbonImmutable::parse($attributes['starts_at']);

        $endsAt = $this->resolveEndsAt($attributes, $startsAt);

        if ($endsAt instanceof CarbonImmutable && $endsAt->lessThanOrEqualTo($startsAt)) {
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
            'pricing_mode' => $attributes['pricing_mode'] ?? null,
            'registration_mode' => $attributes['registration_mode'] ?? null,
            'issue_passes_for_free' => $attributes['issue_passes_for_free'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
        ]);

        event(new EventOccurrenceCreated(
            (string) $occurrence->getKey(),
            (string) $occurrence->event_id,
        ));

        return $occurrence;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveEndsAt(array $attributes, CarbonImmutable $startsAt): ?CarbonImmutable
    {
        if (! array_key_exists('ends_at', $attributes) || $attributes['ends_at'] === '') {
            return $startsAt->addHours(2);
        }

        if ($attributes['ends_at'] === null) {
            return null;
        }

        return CarbonImmutable::parse($attributes['ends_at']);
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
