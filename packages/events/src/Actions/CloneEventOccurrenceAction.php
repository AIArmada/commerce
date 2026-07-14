<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Support\EventWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CloneEventOccurrenceAction
{
    public function __construct(
        private readonly CreateEventOccurrenceAction $createOccurrence,
        private readonly CloneEventSessionAction $cloneSession,
        private readonly CloneEventContentsAction $cloneContents,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function handle(EventOccurrence $occurrence, array $options = []): EventOccurrence
    {
        $targetEvent = $this->resolveEvent($occurrence, $options);
        $cloneSessions = $options['clone_sessions'] ?? true;

        return DB::transaction(function () use ($occurrence, $options, $targetEvent, $cloneSessions): EventOccurrence {
            $title = blank($options['title'] ?? null)
                ? $occurrence->title . ' (Copy)'
                : (string) $options['title'];

            $clone = $this->createOccurrence->handle($targetEvent, [
                'title' => $title,
                'slug' => blank($options['slug'] ?? null)
                    ? Str::slug($title) . '-' . Str::random(6)
                    : (string) $options['slug'],
                'starts_at' => $options['starts_at'] ?? CarbonImmutable::parse($occurrence->starts_at),
                'ends_at' => $options['ends_at'] ?? CarbonImmutable::parse($occurrence->ends_at),
                'timezone' => $options['timezone'] ?? $occurrence->timezone,
                'status' => 'scheduled',
                'visibility' => $options['visibility'] ?? $occurrence->visibility,
                'delivery_mode' => $options['delivery_mode'] ?? $occurrence->delivery_mode,
                'capacity' => $options['capacity'] ?? $occurrence->capacity,
                'metadata' => $options['metadata'] ?? $occurrence->metadata,
            ]);

            $this->cloneContents->handle(
                sourceEventId: $occurrence->event_id,
                targetEventId: $targetEvent->getKey(),
                sourceOccurrenceId: $occurrence->getKey(),
                targetOccurrenceId: $clone->getKey(),
            );

            if ($cloneSessions) {
                $occurrence->loadMissing('sessions');

                foreach ($occurrence->sessions as $session) {
                    $clonedSession = $this->cloneSession->handle($session, [
                        'event_id' => $targetEvent->getKey(),
                        'event_occurrence_id' => $clone->getKey(),
                        'status' => 'scheduled',
                        'clone_children' => false,
                    ]);

                    $this->cloneContents->handle(
                        sourceEventId: $occurrence->event_id,
                        targetEventId: $targetEvent->getKey(),
                        sourceSessionId: $session->getKey(),
                        targetSessionId: $clonedSession->getKey(),
                        sourceOccurrenceId: $occurrence->getKey(),
                        targetOccurrenceId: $clone->getKey(),
                    );
                }
            }

            return $clone;
        });
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveEvent(EventOccurrence $occurrence, array $options): Event
    {
        $targetEventId = $options['target_event_id'] ?? $occurrence->event_id;

        return EventWriteGuard::findOrFail($targetEventId);
    }
}
