<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Events\EventSessionCreated;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Platform\EventContentNormalizer;
use AIArmada\Events\Platform\EventWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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
        $title = blank($attributes['title'] ?? null)
            ? throw new InvalidArgumentException('Session title is required.')
            : $this->contentNormalizer->normalizeTitle((string) $attributes['title']);

        $startsAt = blank($attributes['starts_at'] ?? null)
            ? CarbonImmutable::now()
            : CarbonImmutable::parse($attributes['starts_at']);

        $endsAt = $this->resolveEndsAt($attributes, $startsAt);

        if ($endsAt instanceof CarbonImmutable && $endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException('Session end time must be after the start time.');
        }

        $slugBase = blank($attributes['slug'] ?? null)
            ? Str::slug($title)
            : (string) $attributes['slug'];

        return DB::transaction(function () use ($occurrence, $attributes, $title, $startsAt, $endsAt, $slugBase): EventSession {
            /** @var EventOccurrence $occurrence */
            $occurrence = $occurrence->newQuery()
                ->withoutOwnerScope()
                ->whereKey($occurrence->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $event = $this->resolveEventForWrite((string) $occurrence->getAttribute('event_id'));

            $event::query()
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $slug = $this->uniqueSlugInEvent((string) $event->getKey(), $slugBase);

            $sortOrder = blank($attributes['sort_order'] ?? null)
                ? (int) EventSession::query()
                    ->where('event_occurrence_id', $occurrence->getKey())
                    ->max('sort_order') + 1
                : (int) $attributes['sort_order'];

            $session = EventSession::query()->create([
                'event_id' => $event->getKey(),
                'event_occurrence_id' => $occurrence->getKey(),
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
                'pricing_mode' => $attributes['pricing_mode'] ?? null,
                'registration_mode' => $attributes['registration_mode'] ?? null,
                'issue_passes_for_free' => $attributes['issue_passes_for_free'] ?? null,
                'sort_order' => $sortOrder,
                'metadata' => $attributes['metadata'] ?? null,
            ]);

            event(new EventSessionCreated(
                (string) $session->getKey(),
                (string) $session->event_id,
                $session->event_occurrence_id !== null ? (string) $session->event_occurrence_id : null,
            ));

            return $session;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveEndsAt(array $attributes, CarbonImmutable $startsAt): ?CarbonImmutable
    {
        if (! array_key_exists('ends_at', $attributes) || $attributes['ends_at'] === '') {
            return $startsAt->addHour();
        }

        if ($attributes['ends_at'] === null) {
            return null;
        }

        return CarbonImmutable::parse($attributes['ends_at']);
    }

    private function uniqueSlugInEvent(string $eventId, string $slug): string
    {
        $base = $slug === '' ? 'session' : $slug;

        $query = EventSession::query()
            ->withoutOwnerScope()
            ->where('event_id', $eventId);

        if (str_contains($base, '\\')) {
            // Backslash is the LIKE escape character on PostgreSQL but a
            // literal on SQLite, so no LIKE pattern built from such a base is
            // portable. Fall back to the full event-scoped set; candidacy is
            // still decided by exact in-memory comparison below.
            $existing = $query->pluck('slug')->all();
        } else {
            // Portable superset in one scoped query: exact equality can never
            // miss the base, and the unescaped LIKE prefix can only overfetch
            // within the same event when the base holds LIKE wildcards.
            $existing = $query->where(function ($nested) use ($base): void {
                $nested->where('slug', $base)
                    ->orWhere('slug', 'like', $base . '-%');
            })->pluck('slug')->all();
        }

        $taken = array_fill_keys($existing, true);

        $candidate = $base;
        $suffix = 2;

        if (isset($taken[$candidate])) {
            while (isset($taken[$base . '-' . $suffix])) {
                $suffix++;
            }

            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        while (EventSession::query()->withoutOwnerScope()->where('event_id', $eventId)->where('slug', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function resolveEventForWrite(int | string $eventId): Event
    {
        $resolved = EventWriteGuard::findOrFail($eventId);

        if (! $resolved instanceof Event) {
            throw new InvalidArgumentException('The configured events model must extend the Catalog Event model.');
        }

        return $resolved;
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
