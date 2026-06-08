<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventPerson;
use AIArmada\Events\Models\Occurrence;
use Spatie\LaravelData\Data;

final class EventDetailData extends Data
{
    /**
     * @param  array<string, mixed>  $taxonomy
     * @param  array<string, mixed>  $media
     * @param  array<string, mixed>  $references
     * @param  array<int, string>  $peopleNames
     * @param  array<int, OccurrenceDetailData>  $occurrences
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $summary = null,
        public readonly ?string $description = null,
        public readonly string $status = 'draft',
        public readonly string $moderationStatus = 'pending',
        public readonly string $visibility = 'public',
        public readonly string $structure = 'standalone',
        public readonly ?string $timezone = null,
        public readonly array $taxonomy = [],
        public readonly array $media = [],
        public readonly array $references = [],
        public readonly array $peopleNames = [],
        public readonly ?string $publishedAt = null,
        public readonly ?string $publicStartsAt = null,
        public readonly ?string $publicEndsAt = null,
        public readonly array $occurrences = [],
        public readonly ?array $metadata = null,
        public readonly bool $registrationRequired = false,
    ) {}

    public static function fromEvent(Event $event): self
    {
        $event->loadMissing([
            'classifications',
            'assets',
            'references',
            'people',
            'occurrences.address',
            'occurrences.subLocation',
            'occurrences.references',
            'occurrences.agendaItems',
        ]);

        return new self(
            id: $event->id,
            name: $event->name,
            slug: $event->slug,
            summary: $event->summary,
            description: $event->description,
            status: $event->status->value,
            moderationStatus: $event->moderation_status->value,
            visibility: $event->visibility->value,
            structure: $event->structure->value,
            timezone: $event->default_timezone,
            taxonomy: $event->taxonomyTerms(),
            media: $event->assetReferences(),
            references: $event->referenceMaterials(),
            peopleNames: $event->people
                ->map(static fn (EventPerson $person): ?string => $person->display_name)
                ->filter()
                ->values()
                ->all(),
            publishedAt: $event->published_at?->toISOString(),
            publicStartsAt: $event->public_starts_at?->toISOString(),
            publicEndsAt: $event->public_ends_at?->toISOString(),
            occurrences: $event->occurrences
                ->map(static fn (Occurrence $occurrence): OccurrenceDetailData => OccurrenceDetailData::fromOccurrence($occurrence))
                ->values()
                ->all(),
            metadata: $event->metadata,
            registrationRequired: (bool) $event->registration_required,
        );
    }
}
