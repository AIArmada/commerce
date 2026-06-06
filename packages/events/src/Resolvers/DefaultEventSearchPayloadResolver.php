<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventSearchPayloadResolver;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSpeaker;

final class DefaultEventSearchPayloadResolver implements EventSearchPayloadResolver
{
    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'summary' => $event->summary,
            'description' => $event->description,
            'status' => $event->status->value,
            'moderation_status' => $event->moderation_status->value,
            'visibility' => $event->visibility->value,
            'timezone' => $event->default_timezone,
            'taxonomy' => $event->taxonomy ?? [],
            'media' => $event->media_references ?? [],
            'search_keywords' => $event->search_keywords,
            'speaker_names' => $this->speakerNames($event),
            'published_at' => $event->published_at?->toISOString(),
            'public_starts_at' => $event->public_starts_at?->toISOString(),
            'public_ends_at' => $event->public_ends_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function speakerNames(Event $event): array
    {
        if (! $event->relationLoaded('speakers')) {
            return [];
        }

        return $event->speakers
            ->map(static fn (EventSpeaker $speaker): ?string => $speaker->display_name)
            ->filter()
            ->values()
            ->all();
    }
}
