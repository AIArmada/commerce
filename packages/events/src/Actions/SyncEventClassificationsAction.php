<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventTaxonomy;
use AIArmada\Events\Models\EventTerm;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SyncEventClassificationsAction
{
    /**
     * @param  array<string, list<mixed>>  $taxonomyValues
     * @param  array<string, array{name?: string, description?: string|null, is_hierarchical?: bool, is_active?: bool}>  $taxonomyDefinitions
     * @param  list<mixed>  $explicitTermIds
     */
    public function handle(
        Event $event,
        array $taxonomyValues,
        array $taxonomyDefinitions = [],
        array $explicitTermIds = [],
    ): int {
        $termIds = collect();

        foreach ($taxonomyValues as $taxonomyCode => $values) {
            $definition = $taxonomyDefinitions[$taxonomyCode] ?? [];
            $taxonomy = $this->firstOrCreateTaxonomy($taxonomyCode, $definition);

            foreach ($values as $value) {
                if (is_string($value) && Str::isUuid($value)) {
                    $term = EventTerm::query()
                        ->whereKey($value)
                        ->where('event_taxonomy_id', $taxonomy->getKey())
                        ->first();

                    if ($term instanceof EventTerm) {
                        $termIds->push($term->getKey());
                    }

                    continue;
                }

                $name = is_string($value) ? mb_trim($value) : '';

                if ($name === '') {
                    continue;
                }

                $termIds->push(EventTerm::query()->firstOrCreate(
                    [
                        'event_taxonomy_id' => $taxonomy->getKey(),
                        'code' => Str::slug($name),
                    ],
                    [
                        'name' => $name,
                        'sort_order' => 0,
                        'is_active' => true,
                    ],
                )->getKey());
            }
        }

        $termIds = $termIds
            ->merge($explicitTermIds)
            ->filter(fn (mixed $id): bool => is_string($id) && Str::isUuid($id))
            ->unique()
            ->values();

        EventClassification::query()
            ->where('event_id', $event->getKey())
            ->whereNull('event_occurrence_id')
            ->whereNull('event_session_id')
            ->delete();

        if ($termIds->isEmpty()) {
            return 0;
        }

        /** @var Collection<string, EventTerm> $terms */
        $terms = EventTerm::query()
            ->whereIn('id', $termIds->all())
            ->get()
            ->keyBy(fn (EventTerm $term): string => (string) $term->getKey());

        $synced = 0;

        foreach ($termIds as $sort => $termId) {
            $term = $terms->get((string) $termId);

            if (! $term instanceof EventTerm) {
                continue;
            }

            $taxonomy = EventTaxonomy::query()->find($term->event_taxonomy_id);

            EventClassification::query()->create([
                'event_id' => $event->getKey(),
                'event_taxonomy_id' => $term->event_taxonomy_id,
                'event_term_id' => $term->getKey(),
                'taxonomy_code' => $taxonomy?->code,
                'term_code' => $term->code,
                'is_primary' => $sort === 0,
                'weight' => $term->sort_order ?? $sort,
                'sort_order' => $sort,
            ]);

            $synced++;
        }

        return $synced;
    }

    /**
     * @param  array{name?: string, description?: string|null, is_hierarchical?: bool, is_active?: bool}  $definition
     */
    private function firstOrCreateTaxonomy(string $code, array $definition): EventTaxonomy
    {
        return EventTaxonomy::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $definition['name'] ?? $code,
                'description' => $definition['description'] ?? null,
                'is_hierarchical' => $definition['is_hierarchical'] ?? false,
                'is_active' => $definition['is_active'] ?? true,
            ],
        );
    }
}
