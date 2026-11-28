<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Actions;

use AIArmada\Events\Features\Taxonomy\Models\EventTaxonomy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class ManageEventTaxonomyAction
{
    /** @param array<string, mixed> $data */
    public function save(?string $taxonomyId, array $data, mixed $actor): EventTaxonomy
    {
        return DB::transaction(function () use ($taxonomyId, $data, $actor): EventTaxonomy {
            $taxonomy = $taxonomyId === null
                ? new EventTaxonomy
                : EventTaxonomy::query()->whereKey($taxonomyId)->lockForUpdate()->firstOrFail();

            if (! $actor instanceof Authenticatable) {
                throw new AuthorizationException;
            }

            $subject = $taxonomyId === null ? EventTaxonomy::class : $taxonomy;

            Gate::forUser($actor)->authorize($taxonomyId === null ? 'create' : 'update', $subject);

            $taxonomy->fill(Arr::only($data, ['code', 'name', 'description', 'is_hierarchical', 'is_active', 'metadata']));

            if ($taxonomy->exists && $taxonomy->isDirty('is_hierarchical') && ! $taxonomy->is_hierarchical
                && $taxonomy->terms()->whereNotNull('parent_id')->exists()) {
                throw ValidationException::withMessages(['is_hierarchical' => 'Remove the term hierarchy before making this taxonomy flat.']);
            }

            $taxonomy->save();

            return $taxonomy;
        });
    }
}
