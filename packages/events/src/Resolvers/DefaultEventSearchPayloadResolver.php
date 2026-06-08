<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventSearchPayloadResolver;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventPerson;

final class DefaultEventSearchPayloadResolver implements EventSearchPayloadResolver
{
    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(Event $event): array
    {
        $event->loadMissing(['references']);

        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'summary' => $event->summary,
            'description' => $event->description,
            'status' => $event->status->value,
            'moderation_status' => $event->moderation_status->value,
            'visibility' => $event->visibility->value,
            'structure' => $event->structure->value,
            'parent_event_id' => $event->parent_event_id,
            'timezone' => $event->default_timezone,
            'taxonomy' => $event->taxonomyTerms(),
            'media' => $event->assetReferences(),
            'references' => $event->referenceMaterials(),
            'search_keywords' => $event->search_keywords,
            'people_names' => $this->peopleNames($event),
            'parent_event_name' => $this->stringOrNull($event->relationLoaded('parentEvent') ? $event->parentEvent?->getAttribute('name') : null),
            'child_event_count' => $event->relationLoaded('childEvents') ? $event->childEvents->count() : null,
            'published_at' => $event->published_at?->toISOString(),
            'public_starts_at' => $event->public_starts_at?->toISOString(),
            'public_ends_at' => $event->public_ends_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function peopleNames(Event $event): array
    {
        if ($event->relationLoaded('people')) {
            return $this->personNames($event->people);
        }

        return [];
    }

    /**
     * @param  iterable<int, EventPerson>  $people
     * @return array<int, string>
     */
    private function personNames(iterable $people): array
    {
        return collect($people)
            ->map(static fn (EventPerson $person): ?string => $person->display_name)
            ->filter()
            ->values()
            ->all();
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value !== '' ? $value : null;
    }
}
