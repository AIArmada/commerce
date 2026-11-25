<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Actions;

use AIArmada\Events\Features\Taxonomy\Models\EventClassification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CloneTaxonomyScopeAction
{
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

        $sourceColumn = match ($sourceScopeType) {
            'event' => 'event_id',
            'occurrence' => 'event_occurrence_id',
            'session' => 'event_session_id',
            default => throw new InvalidArgumentException(sprintf('Unknown event scope type [%s].', $sourceScopeType)),
        };

        DB::transaction(function () use ($sourceColumn, $sourceScopeId, $targetScopeId): void {
            EventClassification::query()
                ->where($sourceColumn, $sourceScopeId)
                ->chunkById(200, function (iterable $classifications) use ($sourceColumn, $targetScopeId): void {
                    foreach ($classifications as $classification) {
                        $replica = $classification->replicate(['id', 'created_at', 'updated_at']);
                        $replica->setAttribute($sourceColumn, $targetScopeId);
                        $replica->save();
                    }
                });
        });
    }
}
