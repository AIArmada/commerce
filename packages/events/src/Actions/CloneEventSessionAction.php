<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Events\EventSessionCreated;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\Events\Support\Normalization\EventContentNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            $title = blank($options['title'] ?? null)
                ? $session->title . ' (Copy)'
                : $this->contentNormalizer->normalizeTitle((string) $options['title']);

            $clone = EventSession::query()->create([
                'event_id' => $options['event_id'] ?? $session->event_id,
                'event_occurrence_id' => $options['event_occurrence_id'] ?? $session->event_occurrence_id,
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
                'starts_at' => $options['starts_at'] ?? $session->starts_at,
                'ends_at' => $options['ends_at'] ?? $session->ends_at,
                'timezone' => $options['timezone'] ?? $session->timezone,
                'status' => $options['status'] ?? 'scheduled',
                'visibility' => $options['visibility'] ?? $session->visibility,
                'delivery_mode' => $options['delivery_mode'] ?? $session->delivery_mode,
                'capacity' => $options['capacity'] ?? $session->capacity,
                'sort_order' => $options['sort_order'] ?? $session->sort_order,
                'metadata' => $options['metadata'] ?? $session->metadata,
            ]);

            if ($options['clone_children'] ?? true) {
                $this->cloneContents->handle(
                    sourceEventId: $session->event_id,
                    targetEventId: $options['event_id'] ?? $session->event_id,
                    sourceOccurrenceId: $session->event_occurrence_id,
                    targetOccurrenceId: $options['event_occurrence_id'] ?? $session->event_occurrence_id,
                    sourceSessionId: $session->getKey(),
                    targetSessionId: $clone->getKey(),
                );
            }

            event(new EventSessionCreated($clone));

            return $clone;
        });
    }

    private function resolveEventForWrite(int | string $eventId): Event
    {
        $eventClass = ModelResolver::eventClass();

        if (method_exists($eventClass, 'ownerScopeConfig') && ! $eventClass::ownerScopeConfig()->enabled) {
            return $eventClass::query()->findOrFail($eventId);
        }

        return OwnerWriteGuard::findOrFailForOwner($eventClass, $eventId);
    }
}
