<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventSearchIndexer;
use AIArmada\Events\Contracts\EventSearchPayloadResolver;
use AIArmada\Events\Jobs\BuildEventSearchDocumentJob;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAudience;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSearchDocument;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Support\EventOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class EventSearchDocumentBuilder implements EventSearchIndexer
{
    public function __construct(
        private readonly EventSearchPayloadResolver $payloadResolver,
    ) {}

    public function index(mixed $target): void
    {
        $target = $this->resolveSearchTarget($target);

        if ($target === null) {
            return;
        }

        if (config('events.sync.build_search_documents')) {
            $this->dispatchOrBuild($target);
        }
    }

    public function remove(mixed $target): void
    {
        if ($target instanceof EventSearchDocument) {
            if ($target->event_session_id !== null) {
                $this->unscopedDocumentQuery()
                    ->where('event_session_id', $target->event_session_id)
                    ->delete();

                return;
            }

            if ($target->event_occurrence_id !== null) {
                $this->unscopedDocumentQuery()
                    ->where('event_occurrence_id', $target->event_occurrence_id)
                    ->delete();

                return;
            }

            if ($target->event_id !== null) {
                $this->unscopedDocumentQuery()
                    ->where('event_id', $target->event_id)
                    ->delete();
            }

            return;
        }

        $target = $this->resolveSearchTarget($target);

        if ($target === null) {
            return;
        }

        $this->deleteForTarget($target);
    }

    public function buildForEvent(Event $event): EventSearchDocument
    {
        return $this->buildDocument($event);
    }

    public function buildForOccurrence(EventOccurrence $occurrence): EventSearchDocument
    {
        return $this->buildDocument($occurrence);
    }

    public function buildForSession(EventSession $session): EventSearchDocument
    {
        return $this->buildDocument($session);
    }

    public function buildPayload(Event $event): array
    {
        return $this->buildPayloadForEvent($event);
    }

    public function buildPayloadForEvent(Event $event): array
    {
        return $this->buildPayloadForTarget($event);
    }

    public function buildPayloadForOccurrence(EventOccurrence $occurrence): array
    {
        return $this->buildPayloadForTarget($occurrence);
    }

    public function buildPayloadForSession(EventSession $session): array
    {
        return $this->buildPayloadForTarget($session);
    }

    private function resolveSearchTarget(mixed $target): Event | EventOccurrence | EventSession | null
    {
        if ($target instanceof Event || $target instanceof EventOccurrence || $target instanceof EventSession) {
            return $target;
        }

        if (! $target instanceof EventSearchDocument) {
            return null;
        }

        $searchable = $target->searchable;

        if ($searchable instanceof Event || $searchable instanceof EventOccurrence || $searchable instanceof EventSession) {
            return $searchable;
        }

        return null;
    }

    private function dispatchOrBuild(Event | EventOccurrence | EventSession $target): void
    {
        if (config('events.search.queue_indexing', false)) {
            $connection = config('events.search.queue_connection');
            $queue = config('events.search.queue_name');

            $job = new BuildEventSearchDocumentJob($target);

            if ($connection !== null) {
                $job->onConnection($connection);
            }

            if ($queue !== null) {
                $job->onQueue($queue);
            }

            $job->afterCommit();

            dispatch($job);

            return;
        }

        $this->buildDocument($target);
    }

    private function deleteForTarget(Event | EventOccurrence | EventSession $target): void
    {
        match (true) {
            $target instanceof Event => $this->unscopedDocumentQuery()->where('event_id', $target->id)->delete(),
            $target instanceof EventOccurrence => $this->unscopedDocumentQuery()->where('event_occurrence_id', $target->id)->delete(),
            $target instanceof EventSession => $this->unscopedDocumentQuery()->where('event_session_id', $target->id)->delete(),
        };
    }

    /**
     * Search documents can outlive a just-deleted parent during observer cleanup.
     *
     * @return Builder<EventSearchDocument>
     */
    private function unscopedDocumentQuery(): Builder
    {
        return EventSearchDocument::query()->withoutGlobalScope(EventOwnerScope::class);
    }

    private function buildDocument(Event | EventOccurrence | EventSession $target): EventSearchDocument
    {
        $payload = $this->buildPayloadForTarget($target);

        /** @var EventSearchDocument $doc */
        $doc = EventSearchDocument::updateOrCreate(
            $this->lookupAttributesForTarget($target),
            $payload,
        );

        return $doc;
    }

    /**
     * @return array{event_id: string, document_type: string}|array{event_occurrence_id: string, document_type: string}|array{event_session_id: string, document_type: string}
     */
    private function lookupAttributesForTarget(Event | EventOccurrence | EventSession $target): array
    {
        return match (true) {
            $target instanceof Event => [
                'event_id' => $target->id,
                'document_type' => 'event',
            ],
            $target instanceof EventOccurrence => [
                'event_occurrence_id' => $target->id,
                'document_type' => 'occurrence',
            ],
            $target instanceof EventSession => [
                'event_session_id' => $target->id,
                'document_type' => 'session',
            ],
        };
    }

    private function buildPayloadForTarget(Event | EventOccurrence | EventSession $target): array
    {
        $base = [
            'searchable_type' => $target->getMorphClass(),
            'searchable_id' => $target->id,
            'event_id' => $target->event_id ?? $target->id,
            'event_occurrence_id' => $target instanceof EventOccurrence ? $target->id : ($target instanceof EventSession ? $target->event_occurrence_id : null),
            'event_session_id' => $target instanceof EventSession ? $target->id : null,
            'document_type' => $this->documentTypeForTarget($target),
            'title' => $this->resolveTitleForTarget($target),
            'summary' => $this->resolveSummaryForTarget($target),
            'body' => $this->resolveBodyForTarget($target),
            'facets' => $this->buildFacets($target),
            'keywords' => null,
            'indexed_at' => now(),
            'status' => 'active',
            'metadata' => $target->metadata ?? null,
        ];

        return $this->payloadResolver->resolve($base);
    }

    private function documentTypeForTarget(Event | EventOccurrence | EventSession $target): string
    {
        return match (true) {
            $target instanceof Event => 'event',
            $target instanceof EventOccurrence => 'occurrence',
            $target instanceof EventSession => 'session',
        };
    }

    private function resolveTitleForTarget(Event | EventOccurrence | EventSession $target): ?string
    {
        return $target->title;
    }

    private function resolveSummaryForTarget(Event | EventOccurrence | EventSession $target): ?string
    {
        if ($target instanceof Event) {
            return $target->summary;
        }

        if ($target instanceof EventSession) {
            return $target->summary;
        }

        return $this->metadataString($target->metadata, 'summary');
    }

    private function resolveBodyForTarget(Event | EventOccurrence | EventSession $target): ?string
    {
        if ($target instanceof Event) {
            return $target->description;
        }

        if ($target instanceof EventSession) {
            return $target->description;
        }

        return $this->metadataString($target->metadata, 'description');
    }

    private function buildFacets(Event | EventOccurrence | EventSession $target): array
    {
        $facets = $target->metadata ?? [];

        if (config('events.sync.audiences_to_facets')) {
            $audiences = $this->buildAudienceFacets($target);

            if ($audiences === []) {
                unset($facets['_audiences']);
            } else {
                $facets['_audiences'] = $audiences;
            }
        }

        if (config('events.sync.classifications_to_facets')) {
            $classifications = $this->buildClassificationFacets($target);

            if ($classifications === []) {
                unset($facets['_classifications']);
            } else {
                $facets['_classifications'] = $classifications;
            }
        }

        return $facets;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function buildAudienceFacets(Event | EventOccurrence | EventSession $target): array
    {
        $whitelist = config('events.attribute_sync.audience_types');

        /** @var Collection<int, EventAudience> $audiences */
        $audiences = $this->audienceFacetQuery($target)
            ->when($whitelist !== null, fn ($query) => $query->whereIn('audience_type', $whitelist))
            ->get(['audience_type', 'value']);

        /** @var array<string, array<int, string>> $grouped */
        $grouped = [];

        foreach ($audiences as $audience) {
            if ($audience->value === null) {
                continue;
            }

            $grouped[$audience->audience_type][] = $audience->value;
        }

        foreach ($grouped as $type => $values) {
            $uniqueValues = [];

            foreach ($values as $value) {
                if (in_array($value, $uniqueValues, true)) {
                    continue;
                }

                $uniqueValues[] = $value;
            }

            $grouped[$type] = $uniqueValues;
        }

        return $grouped;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function buildClassificationFacets(Event | EventOccurrence | EventSession $target): array
    {
        $whitelist = config('events.attribute_sync.taxonomy_codes');

        /** @var Collection<int, EventClassification> $classifications */
        $classifications = $this->classificationFacetQuery($target)
            ->when($whitelist !== null, fn ($query) => $query->whereIn('taxonomy_code', $whitelist))
            ->get(['taxonomy_code', 'term_code']);

        /** @var array<string, array<int, string>> $grouped */
        $grouped = [];

        foreach ($classifications as $classification) {
            if ($classification->taxonomy_code === null || $classification->term_code === null) {
                continue;
            }

            $grouped[$classification->taxonomy_code][] = $classification->term_code;
        }

        foreach ($grouped as $taxonomyCode => $terms) {
            $uniqueTerms = [];

            foreach ($terms as $term) {
                if (in_array($term, $uniqueTerms, true)) {
                    continue;
                }

                $uniqueTerms[] = $term;
            }

            $grouped[$taxonomyCode] = $uniqueTerms;
        }

        return $grouped;
    }

    private function metadataString(mixed $metadata, string $key): ?string
    {
        if (! is_array($metadata)) {
            return null;
        }

        $value = $metadata[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private function audienceFacetQuery(Event | EventOccurrence | EventSession $target): Builder
    {
        return match (true) {
            $target instanceof Event => EventAudience::where('event_id', $target->id),
            $target instanceof EventOccurrence => EventAudience::where('event_occurrence_id', $target->id),
            $target instanceof EventSession => EventAudience::where('event_session_id', $target->id),
        };
    }

    private function classificationFacetQuery(Event | EventOccurrence | EventSession $target): Builder
    {
        return match (true) {
            $target instanceof Event => EventClassification::where('event_id', $target->id),
            $target instanceof EventOccurrence => EventClassification::where('event_occurrence_id', $target->id),
            $target instanceof EventSession => EventClassification::where('event_session_id', $target->id),
        };
    }
}
