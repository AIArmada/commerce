<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Events\EventSessionCreated;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSession;
use Illuminate\Support\Str;

final class CloneEventSessionAction
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function handle(EventSession $session, array $options = []): EventSession
    {
        $this->resolveEventForWrite($session->event_id);

        $title = blank($options['title'] ?? null)
            ? $session->title . ' (Copy)'
            : (string) $options['title'];

        $clone = EventSession::query()->create([
            'event_id' => $session->event_id,
            'event_occurrence_id' => $session->event_occurrence_id,
            'title' => $title,
            'slug' => blank($options['slug'] ?? null)
                ? Str::slug($title, '-') . '-' . Str::random(6)
                : (string) $options['slug'],
            'summary' => $options['summary'] ?? $session->summary,
            'description' => $options['description'] ?? $session->description,
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

        event(new EventSessionCreated($clone));

        return $clone;
    }

    private function resolveEventForWrite(int | string $eventId): Event
    {
        if (method_exists(Event::class, 'ownerScopeConfig') && ! Event::ownerScopeConfig()->enabled) {
            return Event::query()->findOrFail($eventId);
        }

        return OwnerWriteGuard::findOrFailForOwner(Event::class, $eventId);
    }
}
