<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues\Actions;

use AIArmada\Events\Features\Venues\Models\EventFacility;
use AIArmada\Events\Features\Venues\Models\EventLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CloneVenuesScopeAction
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
            throw new InvalidArgumentException('Venue scopes can only be cloned between the same scope type.');
        }

        $sourceColumn = $this->columnFor($sourceScopeType);

        DB::transaction(function () use ($sourceColumn, $sourceScopeId, $targetScopeId): void {
            foreach ([EventLocation::class, EventFacility::class] as $modelClass) {
                $modelClass::query()
                    ->where($sourceColumn, $sourceScopeId)
                    ->chunkById(200, function (iterable $children) use ($sourceColumn, $targetScopeId): void {
                        foreach ($children as $child) {
                            if (! $child instanceof Model) {
                                continue;
                            }

                            $replica = $child->replicate(['id', 'created_at', 'updated_at']);
                            $replica->setAttribute($sourceColumn, $targetScopeId);
                            $replica->save();
                        }
                    });
            }
        });
    }

    private function columnFor(string $scopeType): string
    {
        return match ($scopeType) {
            'event' => 'event_id',
            'occurrence' => 'event_occurrence_id',
            'session' => 'event_session_id',
            default => throw new InvalidArgumentException(sprintf('Unknown event scope type [%s].', $scopeType)),
        };
    }
}
