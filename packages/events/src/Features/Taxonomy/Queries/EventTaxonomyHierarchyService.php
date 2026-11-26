<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Queries;

use AIArmada\Events\Contracts\EventTaxonomyHierarchy;
use AIArmada\Events\Data\EventTaxonomyData;
use AIArmada\Events\Data\EventTermData;
use AIArmada\Events\Features\Taxonomy\Models\EventTaxonomy;
use AIArmada\Events\Features\Taxonomy\Models\EventTerm;
use Closure;
use RuntimeException;

final class EventTaxonomyHierarchyService implements EventTaxonomyHierarchy
{
    private const string REQUEST_CACHE_KEY = 'aiarmada.events.taxonomy-hierarchy';

    public static function flushRequestCache(): void
    {
        if (! app()->bound('request')) {
            return;
        }

        request()->attributes->remove(self::REQUEST_CACHE_KEY);
    }

    public function taxonomy(string $code): ?EventTaxonomyData
    {
        $taxonomy = $this->taxonomyModel($code);

        return $taxonomy instanceof EventTaxonomy ? $this->toTaxonomyData($taxonomy) : null;
    }

    public function terms(string $taxonomyCode, bool $activeOnly = true): array
    {
        $taxonomy = $this->taxonomyModel($taxonomyCode);

        if (! $taxonomy instanceof EventTaxonomy || ($activeOnly && ! $taxonomy->is_active)) {
            return [];
        }

        /** @var list<EventTerm> $terms */
        $terms = $this->rememberForRequest(
            "terms:{$taxonomyCode}:" . ($activeOnly ? 'active' : 'all'),
            fn (): array => EventTerm::query()
                ->where('event_taxonomy_id', $taxonomy->getKey())
                ->when($activeOnly, fn ($query) => $query->where('is_active', true))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->all(),
        );

        return array_map($this->toTermData(...), $terms);
    }

    public function tree(string $taxonomyCode, bool $activeOnly = true): array
    {
        $terms = $this->terms($taxonomyCode, $activeOnly);
        $knownIds = [];

        foreach ($terms as $term) {
            $knownIds[$term->id] = true;
        }

        $byParent = [];

        foreach ($terms as $term) {
            $parentId = $term->parentId !== null && isset($knownIds[$term->parentId]) ? $term->parentId : '';
            $byParent[$parentId][] = $term;
        }

        $tree = $this->nodes($byParent, '');

        if (count($this->flatten($tree)) !== count($terms)) {
            throw new RuntimeException('Cycle detected in event taxonomy hierarchy.');
        }

        return $tree;
    }

    public function options(string $taxonomyCode, string $separator = ' › ', bool $activeOnly = true): array
    {
        $options = [];

        foreach ($this->flatten($this->tree($taxonomyCode, $activeOnly)) as $node) {
            $id = $node['id'] ?? null;
            $path = $node['path'] ?? null;

            if (! is_string($id) || ! is_string($path)) {
                continue;
            }

            $options[$id] = $separator === ' › ' ? $path : str_replace(' › ', $separator, $path);
        }

        return $options;
    }

    public function validTermIds(string $taxonomyCode, array $termIds, bool $activeOnly = true): array
    {
        $valid = [];

        foreach ($this->terms($taxonomyCode, $activeOnly) as $term) {
            $valid[$term->id] = true;
        }

        $result = [];

        foreach ($termIds as $id) {
            if (is_scalar($id) && isset($valid[(string) $id])) {
                $result[] = (string) $id;
            }
        }

        return array_values(array_unique($result));
    }

    public function descendantIds(string $taxonomyCode, array $termIds, bool $activeOnly = true): array
    {
        $terms = $this->terms($taxonomyCode, $activeOnly);
        $children = [];

        foreach ($terms as $term) {
            $children[$term->parentId ?? ''][] = $term->id;
        }

        $result = [];

        foreach ($this->validTermIds($taxonomyCode, $termIds, $activeOnly) as $id) {
            $result[] = $id;
            $this->appendDescendants($id, $children, $result, []);
        }

        return array_values(array_unique($result));
    }

    public function minimalTermIds(string $taxonomyCode, array $termIds, bool $activeOnly = true): array
    {
        $ids = $this->validTermIds($taxonomyCode, $termIds, $activeOnly);
        $parents = [];

        foreach ($this->terms($taxonomyCode, $activeOnly) as $term) {
            $parents[$term->id] = $term->parentId;
        }

        $selected = array_fill_keys($ids, true);

        return array_values(array_filter($ids, function (string $id) use ($parents, $selected): bool {
            $parentId = $parents[$id];
            $visited = [];

            while ($parentId !== null) {
                if (isset($visited[$parentId])) {
                    throw new RuntimeException('Cycle detected in event taxonomy hierarchy.');
                }

                $visited[$parentId] = true;

                if (isset($selected[$parentId])) {
                    return false;
                }

                $parentId = $parents[$parentId] ?? null;
            }

            return true;
        }));
    }

    /**
     * @return list<array{id: string, label: string, code: string}>
     */
    public function searchTerms(string $taxonomyCode, ?string $search = null, int $limit = 50): array
    {
        $terms = $this->terms($taxonomyCode, true);
        $normalizedSearch = mb_trim((string) $search);

        if ($normalizedSearch !== '') {
            $terms = array_values(array_filter(
                $terms,
                static fn (EventTermData $term): bool => mb_stripos($term->name, $normalizedSearch) !== false,
            ));
        }

        $terms = array_slice($terms, 0, max(1, $limit));

        return array_map(
            static fn (EventTermData $term): array => [
                'id' => $term->id,
                'label' => $term->name,
                'code' => $term->code,
            ],
            $terms,
        );
    }

    private function taxonomyModel(string $code): ?EventTaxonomy
    {
        /** @var EventTaxonomy|null $taxonomy */
        $taxonomy = $this->rememberForRequest(
            "taxonomy-model:{$code}",
            fn (): ?EventTaxonomy => EventTaxonomy::query()->where('code', $code)->first(),
        );

        return $taxonomy instanceof EventTaxonomy ? $taxonomy : null;
    }

    private function rememberForRequest(string $key, Closure $resolver): mixed
    {
        if (! app()->bound('request')) {
            return $resolver();
        }

        $request = request();
        $cache = $request->attributes->get(self::REQUEST_CACHE_KEY, []);

        if (is_array($cache) && array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $value = $resolver();
        $cache = is_array($cache) ? $cache : [];
        $cache[$key] = $value;
        $request->attributes->set(self::REQUEST_CACHE_KEY, $cache);

        return $value;
    }

    /**
     * @param  array<string, list<EventTermData>>  $byParent
     * @return list<array<string, mixed>>
     */
    private function nodes(array $byParent, string $parentId, string $prefix = ''): array
    {
        $nodes = [];

        foreach ($byParent[$parentId] ?? [] as $term) {
            $path = $prefix === '' ? $term->name : $prefix . ' › ' . $term->name;

            $nodes[] = [
                'id' => $term->id,
                'code' => $term->code,
                'name' => $term->name,
                'parent_id' => $term->parentId,
                'path' => $path,
                'children' => $this->nodes($byParent, $term->id, $path),
            ];
        }

        return $nodes;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function flatten(array $nodes): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            $children = $node['children'] ?? [];
            unset($node['children']);
            $flat[] = $node;

            if (is_array($children)) {
                $flat = [...$flat, ...$this->flatten($children)];
            }
        }

        return $flat;
    }

    /**
     * @param  array<string, list<string>>  $children
     * @param  array<int, string>  $result
     * @param  array<string, bool>  $visited
     */
    private function appendDescendants(string $parentId, array $children, array &$result, array $visited): void
    {
        if (isset($visited[$parentId])) {
            throw new RuntimeException('Cycle detected in event taxonomy hierarchy.');
        }

        $visited[$parentId] = true;

        foreach ($children[$parentId] ?? [] as $childId) {
            $result[] = $childId;
            $this->appendDescendants($childId, $children, $result, $visited);
        }
    }

    private function toTaxonomyData(EventTaxonomy $taxonomy): EventTaxonomyData
    {
        return new EventTaxonomyData(
            id: (string) $taxonomy->getKey(),
            code: (string) $taxonomy->code,
            name: (string) $taxonomy->name,
            description: $taxonomy->description,
            isHierarchical: (bool) $taxonomy->is_hierarchical,
            isActive: (bool) $taxonomy->is_active,
            metadata: is_array($taxonomy->metadata) ? $taxonomy->metadata : null,
        );
    }

    private function toTermData(EventTerm $term): EventTermData
    {
        $parentId = $term->parent_id;

        return new EventTermData(
            id: (string) $term->getKey(),
            taxonomyId: (string) $term->event_taxonomy_id,
            parentId: $parentId !== null ? (string) $parentId : null,
            code: (string) $term->code,
            name: (string) $term->name,
            description: $term->description,
            sortOrder: (int) $term->sort_order,
            isActive: (bool) $term->is_active,
            metadata: is_array($term->metadata) ? $term->metadata : null,
        );
    }
}
