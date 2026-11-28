<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Actions;

use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Taxonomy\Models\EventClassification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CloneTaxonomyScopeAction
{
    public function __construct(private readonly CatalogApi $catalog) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function cloneForScope(
        string $sourceScopeType,
        string $sourceScopeId,
        string $targetScopeType,
        string $targetScopeId,
        array $options = [],
    ): void {
        if ($sourceScopeType !== $targetScopeType) {
            throw new InvalidArgumentException('Classification scopes can only be cloned between the same scope type.');
        }

        if ($sourceScopeId === $targetScopeId) {
            throw new InvalidArgumentException('Cannot clone a scope into itself.');
        }

        $source = $this->catalog->resolveScope($sourceScopeType, $sourceScopeId);
        $target = $this->catalog->resolveScope($targetScopeType, $targetScopeId);

        if ($source === null || $target === null) {
            throw new InvalidArgumentException('Both clone scopes must exist in the current owner scope.');
        }

        DB::transaction(function () use ($source, $target): void {
            EventClassification::query()
                ->where('event_id', $source->eventId)
                ->where('event_occurrence_id', $source->occurrenceId)
                ->where('event_session_id', $source->sessionId)
                ->chunkById(200, function (iterable $classifications) use ($target): void {
                    foreach ($classifications as $classification) {
                        $replica = $classification->replicate(['id', 'created_at', 'updated_at']);
                        $replica->setAttribute('event_id', $target->eventId);
                        $replica->setAttribute('event_occurrence_id', $target->occurrenceId);
                        $replica->setAttribute('event_session_id', $target->sessionId);
                        $replica->save();
                    }
                });
        });
    }
}
