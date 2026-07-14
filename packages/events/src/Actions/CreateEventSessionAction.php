<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Events\EventSessionCreated;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\Events\Support\Normalization\EventContentNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateEventSessionAction
{
    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(EventOccurrence $occurrence, array $attributes = []): EventSession
    {
        $this->resolveEventForWrite($occurrence->event_id);

        $title = blank($attributes['title'] ?? null)
            ? throw new InvalidArgumentException('Session title is required.')
            : $this->contentNormalizer->normalizeTitle((string) $attributes['title']);

        $startsAt = blank($attributes['starts_at'] ?? null)
            ? CarbonImmutable::now()
            : CarbonImmutable::parse($attributes['starts_at']);

        $endsAt = blank($attributes['ends_at'] ?? null)
            ? $startsAt->addHour()
            : CarbonImmutable::parse($attributes['ends_at']);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException('Session end time must be after the start time.');
        }

        $slug = blank($attributes['slug'] ?? null)
            ? Str::slug($title)
            : (string) $attributes['slug'];

        $sortOrder = blank($attributes['sort_order'] ?? null)
            ? (int) EventSession::query()
                ->where('event_occurrence_id', $occurrence->id)
                ->max('sort_order') + 1
            : (int) $attributes['sort_order'];

        $session = EventSession::query()->create([
            'event_id' => $occurrence->event_id,
            'event_occurrence_id' => $occurrence->id,
            'title' => $title,
            'slug' => $slug,
            'summary' => $this->contentNormalizer->normalizeSummary(
                blank($attributes['summary'] ?? null) ? null : (string) $attributes['summary'],
            ),
            'description' => $this->contentNormalizer->normalizeDescription(
                blank($attributes['description'] ?? null) ? null : (string) $attributes['description'],
            ),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => blank($attributes['timezone'] ?? null)
                ? (blank($occurrence->timezone ?? null) ? 'UTC' : $occurrence->timezone)
                : (string) $attributes['timezone'],
            'status' => blank($attributes['status'] ?? null)
                ? 'scheduled'
                : (string) $attributes['status'],
            'visibility' => $this->resolveVisibility($occurrence, $attributes),
            'delivery_mode' => blank($attributes['delivery_mode'] ?? null)
                ? (blank($occurrence->delivery_mode ?? null) ? 'in_person' : $occurrence->delivery_mode)
                : (string) $attributes['delivery_mode'],
            'capacity' => blank($attributes['capacity'] ?? null)
                ? null
                : (int) $attributes['capacity'],
            'sort_order' => $sortOrder,
            'metadata' => $attributes['metadata'] ?? null,
        ]);

        event(new EventSessionCreated($session));

        return $session;
    }

    private function resolveEventForWrite(int | string $eventId): Event
    {
        $eventClass = ModelResolver::eventClass();

        if (method_exists($eventClass, 'ownerScopeConfig') && ! $eventClass::ownerScopeConfig()->enabled) {
            return $eventClass::query()->findOrFail($eventId);
        }

        return OwnerWriteGuard::findOrFailForOwner($eventClass, $eventId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveVisibility(EventOccurrence $occurrence, array $attributes): string
    {
        if (! blank($attributes['visibility'] ?? null)) {
            return (string) $attributes['visibility'];
        }

        if (! blank($occurrence->visibility ?? null)) {
            return $occurrence->visibility;
        }

        if (! blank($occurrence->event?->visibility ?? null)) {
            return $occurrence->event->visibility;
        }

        return Event::PUBLIC;
    }
}
