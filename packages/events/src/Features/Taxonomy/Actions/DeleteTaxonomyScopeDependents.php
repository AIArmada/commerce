<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Actions;

use AIArmada\Events\Features\Taxonomy\Models\EventClassification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeleteTaxonomyScopeDependents
{
    public function deleteForScope(string $scopeType, string $scopeId): void
    {
        if ($scopeType === 'registration') {
            return;
        }

        $column = match ($scopeType) {
            'event' => 'event_id',
            'occurrence' => 'event_occurrence_id',
            'session' => 'event_session_id',
            default => throw new InvalidArgumentException(sprintf('Unknown event scope type [%s].', $scopeType)),
        };

        DB::transaction(function () use ($column, $scopeId): void {
            EventClassification::query()
                ->where($column, $scopeId)
                ->chunkById(200, function (iterable $classifications): void {
                    foreach ($classifications as $classification) {
                        $classification->delete();
                    }
                });
        });
    }
}
