<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Events\Support\Normalization\EventContentNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CloneEventAction
{
    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
        private readonly CloneEventOccurrenceAction $cloneOccurrence,
        private readonly CloneEventContentsAction $cloneContents,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function handle(Event $event, array $options = []): Event
    {
        EventWriteGuard::findOrFail($event);
        $cloneOccurrences = $options['clone_occurrences'] ?? false;

        return DB::transaction(function () use ($event, $options, $cloneOccurrences): Event {
            $title = blank($options['title'] ?? null)
                ? $event->title . ' (Copy)'
                : $this->contentNormalizer->normalizeTitle((string) $options['title']);

            $clone = Event::query()->create([
                'owner_type' => $event->owner_type,
                'owner_id' => $event->owner_id,
                'created_by_type' => $event->created_by_type,
                'created_by_id' => $event->created_by_id,
                'title' => $title,
                'slug' => blank($options['slug'] ?? null)
                    ? Str::slug($title) . '-' . Str::random(6)
                    : (string) $options['slug'],
                'summary' => array_key_exists('summary', $options)
                    ? $this->contentNormalizer->normalizeSummary($options['summary'] !== null ? (string) $options['summary'] : null)
                    : $this->contentNormalizer->normalizeSummary($event->summary),
                'description' => array_key_exists('description', $options)
                    ? $this->contentNormalizer->normalizeDescription($options['description'] !== null ? (string) $options['description'] : null)
                    : $this->contentNormalizer->normalizeDescription($event->description),
                'type' => $event->type,
                'status' => 'draft',
                'visibility' => $options['visibility'] ?? $event->visibility,
                'delivery_mode' => $event->delivery_mode,
                'timezone' => $event->timezone,
                'default_venue_id' => $event->default_venue_id,
                'pricing_mode' => $event->pricing_mode,
                'registration_mode' => $event->registration_mode,
                'issue_passes_for_free' => $event->issue_passes_for_free ?? true,
                'metadata' => $event->metadata,
            ]);

            $this->cloneContents->handle(
                sourceEventId: $event->getKey(),
                targetEventId: $clone->getKey(),
            );

            if ($cloneOccurrences) {
                $event->loadMissing('occurrences');

                foreach ($event->occurrences as $occurrence) {
                    $this->cloneOccurrence->handle($occurrence, [
                        'target_event_id' => $clone->getKey(),
                        'clone_sessions' => true,
                    ]);
                }
            }

            return $clone;
        });
    }
}
