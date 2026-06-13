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
        OwnerWriteGuard::findOrFailForOwner(Event::class, $session->event_id);

        $clone = EventSession::query()->create([
            'event_id' => $session->event_id,
            'event_occurrence_id' => $session->event_occurrence_id,
            'title' => $options['title'] ?? $session->title . ' (Copy)',
            'slug' => $options['slug'] ?? Str::slug($options['title'] ?? $session->title . ' Copy', '-') . '-' . Str::random(6),
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
}
