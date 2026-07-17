<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventTaxonomyHierarchy;
use AIArmada\Events\Models\EventTaxonomy;
use AIArmada\Events\Models\EventTerm;
use Illuminate\Support\Collection;
use RuntimeException;

final class EventTaxonomyHierarchyService implements EventTaxonomyHierarchy
{
    public function taxonomy(string $code): ?EventTaxonomy
    {
        return EventTaxonomy::query()->where('code', $code)->first();
    }

    public function terms(string $taxonomyCode, bool $activeOnly = true): Collection
    {
        $taxonomy = $this->taxonomy($taxonomyCode);
        if (! $taxonomy instanceof EventTaxonomy) {
            return collect();
        }

        if ($activeOnly && ! $taxonomy->is_active) {
            return collect();
        }

        return EventTerm::query()
            ->where('event_taxonomy_id', $taxonomy->getKey())
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function tree(string $taxonomyCode, bool $activeOnly = true): array
    {
        $terms = $this->terms($taxonomyCode, $activeOnly);
        $knownIds = $terms->mapWithKeys(fn (EventTerm $term): array => [(string) $term->getKey() => true]);
        $byParent = $terms->groupBy(function (EventTerm $term) use ($knownIds): string {
            $parentId = $term->parent_id === null ? '' : (string) $term->parent_id;

            return $parentId !== '' && $knownIds->has($parentId) ? $parentId : '';
        });

        $tree = $this->nodes($byParent, '');
        if (count($this->flatten($tree)) !== $terms->count()) {
            throw new RuntimeException('Cycle detected in event taxonomy hierarchy.');
        }

        return $tree;
    }

    public function options(string $taxonomyCode, string $separator = ' › ', bool $activeOnly = true): array
    {
        $options = [];
        foreach ($this->flatten($this->tree($taxonomyCode, $activeOnly)) as $node) {
            $options[(string) $node['id']] = (string) $node['path'];
        }

        if ($separator !== ' › ') {
            foreach ($options as $id => $path) {
                $options[$id] = str_replace(' › ', $separator, $path);
            }
        }

        return $options;
    }

    public function validTermIds(string $taxonomyCode, array $termIds, bool $activeOnly = true): array
    {
        $valid = $this->terms($taxonomyCode, $activeOnly)->keyBy(fn (EventTerm $term): string => (string) $term->getKey());

        return array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $id): ?string => is_scalar($id) ? (string) $id : null,
                $termIds,
            ),
            fn (mixed $id): bool => is_string($id) && $valid->has($id),
        )));
    }

    public function descendantIds(string $taxonomyCode, array $termIds, bool $activeOnly = true): array
    {
        $terms = $this->terms($taxonomyCode, $activeOnly);
        $children = $terms->groupBy(fn (EventTerm $term): string => (string) ($term->parent_id ?? ''));
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
        $terms = $this->terms($taxonomyCode, $activeOnly)->keyBy(fn (EventTerm $term): string => (string) $term->getKey());
        $selected = array_fill_keys($ids, true);

        return array_values(array_filter($ids, function (string $id) use ($terms, $selected): bool {
            $term = $terms->get($id);
            $visited = [];
            while ($term instanceof EventTerm && $term->parent_id !== null) {
                $parentId = (string) $term->parent_id;
                if (isset($visited[$parentId])) {
                    throw new RuntimeException('Cycle detected in event taxonomy hierarchy.');
                }
                $visited[$parentId] = true;
                if (isset($selected[$parentId])) {
                    return false;
                }
                $term = $terms->get($parentId);
            }

            return true;
        }));
    }

    /** @param Collection<string, Collection<int, EventTerm>> $byParent @return list<array<string,mixed>> */
    private function nodes(Collection $byParent, string $parentId, string $prefix = ''): array
    {
        $nodes = [];
        foreach ($byParent->get($parentId, collect()) as $term) {
            $path = $prefix === '' ? (string) $term->name : $prefix . ' › ' . $term->name;
            $nodes[] = [
                'id' => (string) $term->getKey(),
                'code' => (string) $term->code,
                'name' => (string) $term->name,
                'parent_id' => $term->parent_id !== null ? (string) $term->parent_id : null,
                'path' => $path,
                'children' => $this->nodes($byParent, (string) $term->getKey(), $path),
            ];
        }

        return $nodes;
    }

    /** @param list<array<string,mixed>> $nodes @return list<array<string,mixed>> */
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

    /** @param Collection<string, Collection<int, EventTerm>> $children @param array<int, string> $result @param array<string, bool> $visited */
    private function appendDescendants(string $parentId, Collection $children, array &$result, array $visited): void
    {
        if (isset($visited[$parentId])) {
            throw new RuntimeException('Cycle detected in event taxonomy hierarchy.');
        }
        $visited[$parentId] = true;
        foreach ($children->get($parentId, collect()) as $child) {
            $childId = (string) $child->getKey();
            $result[] = $childId;
            $this->appendDescendants($childId, $children, $result, $visited);
        }
    }
}
