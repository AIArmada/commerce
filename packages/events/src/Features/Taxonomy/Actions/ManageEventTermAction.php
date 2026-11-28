<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Actions;

use AIArmada\Events\Features\Taxonomy\Models\EventTaxonomy;
use AIArmada\Events\Features\Taxonomy\Models\EventTerm;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ManageEventTermAction
{
    /** @param array<string, mixed> $data */
    public function save(string $taxonomyId, ?string $termId, array $data, mixed $actor): EventTerm
    {
        return DB::transaction(function () use ($taxonomyId, $termId, $data, $actor): EventTerm {
            $taxonomy = EventTaxonomy::query()->whereKey($taxonomyId)->lockForUpdate()->firstOrFail();
            $term = $termId === null
                ? new EventTerm(['event_taxonomy_id' => $taxonomyId])
                : EventTerm::query()->where('event_taxonomy_id', $taxonomyId)->whereKey($termId)->lockForUpdate()->firstOrFail();

            $this->authorize($actor, $termId === null ? 'create' : 'update', $termId === null ? EventTerm::class : $term);
            $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $term->parent_id;
            $this->validateParent($taxonomy, $termId, $parentId);

            $term->fill(Arr::only($data, ['code', 'name', 'description', 'sort_order', 'is_active', 'metadata']));
            $term->parent_id = $parentId;
            $term->save();

            return $term;
        });
    }

    public function delete(string $taxonomyId, string $termId, mixed $actor): bool
    {
        return DB::transaction(function () use ($taxonomyId, $termId, $actor): bool {
            EventTaxonomy::query()->whereKey($taxonomyId)->lockForUpdate()->firstOrFail();
            $term = EventTerm::query()->where('event_taxonomy_id', $taxonomyId)->whereKey($termId)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, 'delete', $term);

            return (bool) $term->delete();
        });
    }

    private function validateParent(EventTaxonomy $taxonomy, ?string $termId, mixed $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if (! $taxonomy->is_hierarchical || ! is_string($parentId) || ! Str::isUuid($parentId)) {
            $this->invalidParent();
        }

        $visited = $termId === null ? [] : [$termId => true];

        while ($parentId !== null) {
            if (isset($visited[$parentId])) {
                $this->invalidParent();
            }

            $visited[$parentId] = true;
            $parent = EventTerm::query()->where('event_taxonomy_id', $taxonomy->id)->whereKey($parentId)->first(['id', 'parent_id']);

            if (! $parent instanceof EventTerm) {
                $this->invalidParent();
            }

            $parentId = $parent->parent_id;
        }
    }

    /** @param class-string<EventTerm>|EventTerm $subject */
    private function authorize(mixed $actor, string $ability, string | EventTerm $subject): void
    {
        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException;
        }

        Gate::forUser($actor)->authorize($ability, $subject);
    }

    private function invalidParent(): never
    {
        throw ValidationException::withMessages(['parent_id' => 'The parent must belong to the same hierarchical taxonomy and cannot create a cycle.']);
    }
}
