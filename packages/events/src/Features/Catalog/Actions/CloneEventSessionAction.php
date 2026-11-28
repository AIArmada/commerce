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

final class CloneEventSessionAction
{
    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
        private readonly CloneEventContentsAction $cloneContents,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function handle(EventSession $session, array $options = []): EventSession
    {
        $this->resolveEventForWrite($session->event_id);

        return DB::transaction(function () use ($session, $options): EventSession {
            $targetEvent = $this->resolveEventForWrite($options['event_id'] ?? $session->event_id);
            $targetOccurrence = EventOccurrence::query()
                ->where('event_id', $targetEvent->getKey())
                ->whereKey($options['event_occurrence_id'] ?? $session->event_occurrence_id)
                ->firstOrFail();

            $startsAt = $options['starts_at'] ?? $session->starts_at;
            $endsAt = array_key_exists('ends_at', $options) ? $options['ends_at'] : $session->ends_at;

            if ($startsAt !== null && $endsAt !== null && CarbonImmutable::parse($endsAt)->lessThanOrEqualTo(CarbonImmutable::parse($startsAt))) {
                throw new InvalidArgumentException('Session end time must be after the start time.');
            }

            $title = blank($options['title'] ?? null)
                ? $session->title . ' (Copy)'
                : $this->contentNormalizer->normalizeTitle((string) $options['title']);

            $clone = EventSession::query()->create([
                'event_id' => $targetEvent->getKey(),
                'event_occurrence_id' => $targetOccurrence->getKey(),
                'title' => $title,
                'slug' => blank($options['slug'] ?? null)
                    ? Str::slug($title, '-') . '-' . Str::random(6)
                    : (string) $options['slug'],
                'summary' => array_key_exists('summary', $options)
                    ? $this->contentNormalizer->normalizeSummary($options['summary'] !== null ? (string) $options['summary'] : null)
                    : $this->contentNormalizer->normalizeSummary($session->summary),
                'description' => array_key_exists('description', $options)
                    ? $this->contentNormalizer->normalizeDescription($options['description'] !== null ? (string) $options['description'] : null)
                    : $this->contentNormalizer->normalizeDescription($session->description),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $options['timezone'] ?? $session->timezone,
                'status' => $options['status'] ?? 'scheduled',
                'visibility' => $options['visibility'] ?? $session->visibility,
                'delivery_mode' => $options['delivery_mode'] ?? $session->delivery_mode,
                'capacity' => $options['capacity'] ?? $session->capacity,
                'pricing_mode' => array_key_exists('pricing_mode', $options) ? $options['pricing_mode'] : $session->pricing_mode,
                'registration_mode' => array_key_exists('registration_mode', $options) ? $options['registration_mode'] : $session->registration_mode,
                'issue_passes_for_free' => array_key_exists('issue_passes_for_free', $options) ? $options['issue_passes_for_free'] : $session->issue_passes_for_free,
                'sort_order' => $options['sort_order'] ?? $session->sort_order,
                'metadata' => $options['metadata'] ?? $session->metadata,
            ]);

            if ($options['clone_children'] ?? true) {
                $this->cloneContents->handle(
                    sourceEventId: $session->event_id,
                    targetEventId: $targetEvent->getKey(),
                    sourceOccurrenceId: $session->event_occurrence_id,
                    targetOccurrenceId: $targetOccurrence->getKey(),
                    sourceSessionId: $session->getKey(),
                    targetSessionId: $clone->getKey(),
                    options: $options,
                );
            }

            DB::afterCommit(static fn () => event(new EventSessionCreated(
                (string) $clone->getKey(),
                (string) $clone->event_id,
                $clone->event_occurrence_id !== null ? (string) $clone->event_occurrence_id : null,
            )));

            return $clone;
        });
    }

    private function resolveEventForWrite(int | string $eventId): Event
    {
        $resolved = EventWriteGuard::findOrFail($eventId);

        if (! $resolved instanceof Event) {
            throw new InvalidArgumentException('The configured events model must extend the Catalog Event model.');
        }

        return $resolved;
    }
}
