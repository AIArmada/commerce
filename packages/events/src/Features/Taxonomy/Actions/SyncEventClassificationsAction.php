<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Taxonomy\Models\EventClassification;
use AIArmada\Events\Features\Taxonomy\Models\EventTaxonomy;
use AIArmada\Events\Features\Taxonomy\Models\EventTerm;
use AIArmada\Events\Platform\EventScopeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SyncEventClassificationsAction
{
    /**
     * @param  array<string, list<mixed>>  $taxonomyValues
     * @param  array<string, array{name?: string, description?: string|null, is_hierarchical?: bool, is_active?: bool}>  $taxonomyDefinitions
     * @param  list<mixed>  $explicitTermIds
     */
    public function handle(
        Model $scope,
        array $taxonomyValues,
        array $taxonomyDefinitions = [],
        array $explicitTermIds = [],
    ): int {
        $resolved = EventScopeResolver::resolve($scope);
        $eventId = $resolved['event_id'];
        $occurrenceId = $resolved['occurrence_id'];
        $sessionId = $resolved['session_id'];

        return DB::transaction(function () use ($eventId, $occurrenceId, $sessionId, $taxonomyValues, $taxonomyDefinitions, $explicitTermIds): int {
            $termIds = $this->resolveTermIds($taxonomyValues, $taxonomyDefinitions, $explicitTermIds);

            $delete = EventClassification::query()->where('event_id', $eventId);

            if ($occurrenceId === null) {
                $delete->whereNull('event_occurrence_id');
            } else {
                $delete->where('event_occurrence_id', $occurrenceId);
            }

            if ($sessionId === null) {
                $delete->whereNull('event_session_id');
            } else {
                $delete->where('event_session_id', $sessionId);
            }

            $delete->delete();

            if ($termIds->isEmpty()) {
                return 0;
            }

            /** @var Collection<string, EventTerm> $terms */
            $terms = EventTerm::query()
                ->whereIn('id', $termIds->all())
                ->get()
                ->keyBy(fn (EventTerm $term): string => (string) $term->getKey());

            /** @var Collection<string, EventTaxonomy> $taxonomies */
            $taxonomies = EventTaxonomy::query()
                ->whereIn('id', $terms->map(fn (EventTerm $term): string => (string) $term->event_taxonomy_id)->unique()->all())
                ->get()
                ->keyBy(fn (EventTaxonomy $taxonomy): string => (string) $taxonomy->getKey());

            $synced = 0;

            foreach ($termIds as $sort => $termId) {
                $term = $terms->get((string) $termId);

                if (! $term instanceof EventTerm) {
                    continue;
                }

                EventClassification::query()->create([
                    'event_id' => $eventId,
                    'event_occurrence_id' => $occurrenceId,
                    'event_session_id' => $sessionId,
                    'event_taxonomy_id' => $term->event_taxonomy_id,
                    'event_term_id' => $term->getKey(),
                    'taxonomy_code' => $taxonomies->get((string) $term->event_taxonomy_id)?->code,
                    'term_code' => $term->code,
                    'is_primary' => $sort === 0,
                    'weight' => $term->sort_order ?? $sort,
                    'sort_order' => $sort,
                ]);

                $synced++;
            }

            return $synced;
        });
    }

    /**
     * @param  array<string, list<mixed>>  $taxonomyValues
     * @param  array<string, array{name?: string, description?: string|null, is_hierarchical?: bool, is_active?: bool}>  $taxonomyDefinitions
     * @param  list<mixed>  $explicitTermIds
     * @return Collection<int, non-empty-string>
     */
    private function resolveTermIds(array $taxonomyValues, array $taxonomyDefinitions, array $explicitTermIds): Collection
    {
        $uuidValues = [];

        foreach ($taxonomyValues as $values) {
            foreach ($values as $value) {
                if (is_string($value) && Str::isUuid($value)) {
                    $uuidValues[] = $value;
                }
            }
        }

        /** @var Collection<string, EventTerm> $uuidTerms */
        $uuidTerms = $uuidValues === []
            ? new Collection
            : EventTerm::query()
                ->whereIn('id', array_unique($uuidValues))
                ->get()
                ->keyBy(fn (EventTerm $term): string => (string) $term->getKey());

        $termIds = new Collection;

        foreach ($taxonomyValues as $taxonomyCode => $values) {
            $definition = $taxonomyDefinitions[$taxonomyCode] ?? [];
            $taxonomy = $this->firstOrCreateTaxonomy($taxonomyCode, $definition);

            foreach ($values as $value) {
                if (is_string($value) && Str::isUuid($value)) {
                    $term = $uuidTerms->get($value);

                    if ($term instanceof EventTerm && (string) $term->event_taxonomy_id === (string) $taxonomy->getKey()) {
                        $termIds->push($term->getKey());
                    }

                    continue;
                }

                $name = is_string($value) ? mb_trim($value) : '';

                if ($name === '') {
                    continue;
                }

                $termIds->push($this->firstOrCreateTerm($taxonomy, $name)->getKey());
            }
        }

        return $termIds
            ->merge($explicitTermIds)
            ->filter(fn (mixed $id): bool => is_string($id) && Str::isUuid($id))
            ->unique()
            ->values();
    }

    /**
     * @param  array{name?: string, description?: string|null, is_hierarchical?: bool, is_active?: bool}  $definition
     */
    private function firstOrCreateTaxonomy(string $code, array $definition): EventTaxonomy
    {
        // Taxonomies are intentional global vocabularies shared across
        // owners, so creation runs in explicit global context.
        return OwnerContext::withOwner(null, function () use ($code, $definition): EventTaxonomy {
            try {
                // The savepoint keeps a lost unique race from aborting the
                // surrounding transaction on PostgreSQL.
                return DB::transaction(fn (): EventTaxonomy => EventTaxonomy::query()->firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $definition['name'] ?? $code,
                        'description' => $definition['description'] ?? null,
                        'is_hierarchical' => $definition['is_hierarchical'] ?? false,
                        'is_active' => $definition['is_active'] ?? true,
                    ],
                ));
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }

                return EventTaxonomy::query()->where('code', $code)->firstOrFail();
            }
        });
    }

    private function firstOrCreateTerm(EventTaxonomy $taxonomy, string $name): EventTerm
    {
        // Terms are intentional global vocabularies shared across owners,
        // so creation runs in explicit global context.
        return OwnerContext::withOwner(null, function () use ($taxonomy, $name): EventTerm {
            $code = Str::slug($name);

            if ($code === '') {
                $code = mb_substr($name, 0, 255);
            }

            try {
                return DB::transaction(fn (): EventTerm => EventTerm::query()->firstOrCreate(
                    [
                        'event_taxonomy_id' => $taxonomy->getKey(),
                        'code' => $code,
                    ],
                    [
                        'name' => $name,
                        'sort_order' => 0,
                        'is_active' => true,
                    ],
                ));
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception)) {
                    throw $exception;
                }

                return EventTerm::query()
                    ->where('event_taxonomy_id', $taxonomy->getKey())
                    ->where('code', $code)
                    ->firstOrFail();
            }
        });
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();

        if ($code === '23000' || $code === '23505') {
            return true;
        }

        return str_contains($exception->getMessage(), '23505');
    }
}
