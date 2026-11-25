<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues\Actions;

use AIArmada\Events\Features\Venues\Models\EventFacility;
use AIArmada\Events\Features\Venues\Models\EventLocation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeleteVenuesScopeDependents
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
            foreach ([EventLocation::class, EventFacility::class] as $modelClass) {
                $modelClass::query()
                    ->where($column, $scopeId)
                    ->chunkById(200, function (iterable $children): void {
                        foreach ($children as $child) {
                            $child->delete();
                        }
                    });
            }
        });
    }
}
