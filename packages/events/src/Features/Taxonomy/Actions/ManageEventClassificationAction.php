<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Actions;

use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Taxonomy\Models\EventClassification;
use AIArmada\Events\Features\Taxonomy\Models\EventTerm;
use AIArmada\Events\Platform\EventWriteGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ManageEventClassificationAction
{
    public function __construct(private CatalogApi $catalog) {}

    /** @param array<string, mixed> $data */
    public function save(string $eventId, ?string $classificationId, array $data, mixed $actor): EventClassification
    {
        return DB::transaction(function () use ($eventId, $classificationId, $data, $actor): EventClassification {
            $this->authorize($eventId, $actor);

            $classification = $classificationId === null
                ? new EventClassification(['event_id' => $eventId])
                : $this->records($eventId)->whereKey($classificationId)->lockForUpdate()->firstOrFail();

            $taxonomyId = $classification->event_taxonomy_id ?? $data['event_taxonomy_id'] ?? null;
            $termId = $data['event_term_id'] ?? $classification->event_term_id;

            if (! is_string($taxonomyId) || ! Str::isUuid($taxonomyId)
                || ! is_string($termId) || ! Str::isUuid($termId)) {
                $this->invalidTerm();
            }

            $term = EventTerm::query()->with('taxonomy')
                ->whereKey($termId)
                ->where('event_taxonomy_id', $taxonomyId)
                ->first();

            if (! $term instanceof EventTerm || $term->taxonomy === null) {
                $this->invalidTerm();
            }

            $classification->fill([
                ...Arr::only($data, ['is_primary', 'sort_order']),
                'event_taxonomy_id' => $term->event_taxonomy_id,
                'event_term_id' => $term->id,
                'taxonomy_code' => $term->taxonomy->code,
                'term_code' => $term->code,
            ])->save();

            return $classification;
        });
    }

    public function delete(string $eventId, string $classificationId, mixed $actor): bool
    {
        return DB::transaction(function () use ($eventId, $classificationId, $actor): bool {
            $this->authorize($eventId, $actor);

            return (bool) $this->records($eventId)->whereKey($classificationId)->lockForUpdate()->firstOrFail()->delete();
        });
    }

    private function authorize(string $eventId, mixed $actor): void
    {
        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException;
        }

        $event = EventWriteGuard::findOrFail($eventId);
        Gate::forUser($actor)->authorize('update', $event);
        $this->catalog->lockEvent($eventId);
    }

    /** @return Builder<EventClassification> */
    private function records(string $eventId): Builder
    {
        return EventClassification::query()
            ->where('event_id', $eventId)
            ->whereNull('event_occurrence_id')
            ->whereNull('event_session_id');
    }

    private function invalidTerm(): never
    {
        throw ValidationException::withMessages(['event_term_id' => 'The selected term does not belong to the selected taxonomy.']);
    }
}
