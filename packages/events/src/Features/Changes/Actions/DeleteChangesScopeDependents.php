<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Changes\Actions;

use AIArmada\Events\Features\Changes\Models\EventChangeLog;
use AIArmada\Events\Features\Changes\Models\EventUpdate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeleteChangesScopeDependents
{
    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
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
            $this->deleteThroughModels(EventUpdate::class, $column, $scopeId);
            $this->deleteThroughModels(EventChangeLog::class, $column, $scopeId);
        });
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function deleteThroughModels(string $modelClass, string $column, string $scopeId): void
    {
        $modelClass::query()
            ->where($column, $scopeId)
            ->chunkById(200, function (iterable $children): void {
                foreach ($children as $child) {
                    $child->delete();
                }
            });
    }
}
