<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Search\Queries;

use AIArmada\Events\Contracts\EventSearchDocumentBuilder as EventSearchDocumentBuilderContract;
use AIArmada\Events\Contracts\EventSearchPayloadResolver;
use AIArmada\Events\Contracts\EventSearchRelationProvider;
use AIArmada\Events\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class EventSearchDocumentBuilder implements EventSearchDocumentBuilderContract
{
    public function __construct(
        private readonly EventSearchPayloadResolver $payloadResolver,
        private readonly EventSearchRelationProvider $relationProvider,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildForEvent(string $eventId): array
    {
        return $this->buildForTarget($this->findTarget(ModelResolver::eventClass(), $eventId), 'event');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildForOccurrence(string $occurrenceId): array
    {
        return $this->buildForTarget($this->findTarget(ModelResolver::occurrenceClass(), $occurrenceId), 'occurrence');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildForSession(string $sessionId): array
    {
        return $this->buildForTarget($this->findTarget(ModelResolver::sessionClass(), $sessionId), 'session');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildForTarget(Model $target, string $documentType): array
    {
        $base = [
            'searchable_type' => $target->getMorphClass(),
            'searchable_id' => (string) $target->getKey(),
            'event_id' => $documentType === 'event'
                ? (string) $target->getKey()
                : (string) $target->getAttribute('event_id'),
            'event_occurrence_id' => match ($documentType) {
                'occurrence' => (string) $target->getKey(),
                'session' => $this->nullableId($target->getAttribute('event_occurrence_id')),
                default => null,
            },
            'event_session_id' => $documentType === 'session' ? (string) $target->getKey() : null,
            'document_type' => $documentType,
            'title' => $this->stringOrNull($target->getAttribute('title')),
            'summary' => in_array($documentType, ['event', 'session'], true)
                ? $this->stringOrNull($target->getAttribute('summary'))
                : null,
            'body' => in_array($documentType, ['event', 'session'], true)
                ? $this->stringOrNull($target->getAttribute('description'))
                : null,
            'keywords' => null,
            'facets' => $this->facetsFor($target, $documentType),
            'indexed_at' => CarbonImmutable::now(),
            'status' => 'active',
            'metadata' => $target->getAttribute('metadata'),
        ];

        return $this->payloadResolver->resolve($base);
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function findTarget(string $class, string $id): Model
    {
        return $class::query()
            ->with($this->relationProvider->relations())
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function facetsFor(Model $target, string $documentType): array
    {
        $facets = $this->buildAttributeFacets($target, $documentType);

        if (config('events.sync.audiences_to_facets')) {
            $audiences = $this->buildAudienceFacets($target, $documentType);

            if ($audiences !== []) {
                $facets['_audiences'] = $audiences;
            }
        }

        if (config('events.sync.classifications_to_facets')) {
            $classifications = $this->buildClassificationFacets($target, $documentType);

            if ($classifications !== []) {
                $facets['_classifications'] = $classifications;
            }
        }

        return $facets;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAttributeFacets(Model $target, string $documentType): array
    {
        $whitelist = config('events.attribute_sync.attribute_keys');
        $query = $this->attributeFacetQuery($target, $documentType);

        if (is_array($whitelist)) {
            $query->whereIn('attribute_key', $whitelist);
        }

        $facets = [];

        foreach ($query->get(['attribute_key', 'attribute_value', 'attribute_value_json']) as $attribute) {
            $key = $attribute->getAttribute('attribute_key');
            $value = $attribute->getAttribute('attribute_value_json') ?? $attribute->getAttribute('attribute_value');

            if (is_string($key) && $key !== '' && $value !== null) {
                $facets[$key] = $value;
            }
        }

        return $facets;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function buildAudienceFacets(Model $target, string $documentType): array
    {
        $whitelist = config('events.attribute_sync.audience_types');
        $query = $this->audienceFacetQuery($target, $documentType);

        if (is_array($whitelist)) {
            $query->whereIn('audience_type', $whitelist);
        }

        /** @var array<string, array<int, string>> $grouped */
        $grouped = [];

        foreach ($query->get(['audience_type', 'value']) as $audience) {
            $type = $audience->getAttribute('audience_type');
            $value = $audience->getAttribute('value');

            if (! is_string($type) || ! is_string($value) || $value === '') {
                continue;
            }

            if (! in_array($value, $grouped[$type] ?? [], true)) {
                $grouped[$type][] = $value;
            }
        }

        return $grouped;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function buildClassificationFacets(Model $target, string $documentType): array
    {
        $whitelist = config('events.attribute_sync.taxonomy_codes');
        $query = $this->classificationFacetQuery($target, $documentType);

        if (is_array($whitelist)) {
            $query->whereIn('taxonomy_code', $whitelist);
        }

        /** @var array<string, array<int, string>> $grouped */
        $grouped = [];

        foreach ($query->get(['taxonomy_code', 'term_code']) as $classification) {
            $taxonomyCode = $classification->getAttribute('taxonomy_code');
            $termCode = $classification->getAttribute('term_code');

            if (! is_string($taxonomyCode) || $taxonomyCode === '' || ! is_string($termCode) || $termCode === '') {
                continue;
            }

            if (! in_array($termCode, $grouped[$taxonomyCode] ?? [], true)) {
                $grouped[$taxonomyCode][] = $termCode;
            }
        }

        return $grouped;
    }

    /**
     * @return Builder<Model>
     */
    private function attributeFacetQuery(Model $target, string $documentType): Builder
    {
        $attributeClass = ModelResolver::attributeClass();
        $query = $attributeClass::query()->where($this->scopeColumn($documentType), $target->getKey());

        return match ($documentType) {
            'event' => $query->whereNull('event_occurrence_id')->whereNull('event_session_id'),
            'occurrence' => $query->whereNull('event_session_id'),
            default => $query,
        };
    }

    /**
     * @return Builder<Model>
     */
    private function audienceFacetQuery(Model $target, string $documentType): Builder
    {
        $audienceClass = ModelResolver::audienceClass();

        return $audienceClass::query()->where($this->scopeColumn($documentType), $target->getKey());
    }

    /**
     * @return Builder<Model>
     */
    private function classificationFacetQuery(Model $target, string $documentType): Builder
    {
        $classificationClass = ModelResolver::classificationClass();

        return $classificationClass::query()->where($this->scopeColumn($documentType), $target->getKey());
    }

    private function scopeColumn(string $documentType): string
    {
        return match ($documentType) {
            'occurrence' => 'event_occurrence_id',
            'session' => 'event_session_id',
            default => 'event_id',
        };
    }

    private function nullableId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
