<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues\Actions;

use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Venues\Models\EventFacility;
use AIArmada\Events\Features\Venues\Models\EventLocation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CloneVenuesScopeAction
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
            throw new InvalidArgumentException('Venue scopes can only be cloned between the same scope type.');
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
            $locationIds = [];

            EventLocation::query()
                ->where('event_id', $source->eventId)
                ->where('event_occurrence_id', $source->occurrenceId)
                ->where('event_session_id', $source->sessionId)
                ->chunkById(200, function (iterable $locations) use ($target, &$locationIds): void {
                    foreach ($locations as $location) {
                        $replica = $location->replicate(['id', 'created_at', 'updated_at']);
                        $replica->setAttribute('event_id', $target->eventId);
                        $replica->setAttribute('event_occurrence_id', $target->occurrenceId);
                        $replica->setAttribute('event_session_id', $target->sessionId);
                        $replica->save();
                        $locationIds[(string) $location->getKey()] = $replica->getKey();
                    }
                });

            EventFacility::query()
                ->where('event_id', $source->eventId)
                ->where('event_occurrence_id', $source->occurrenceId)
                ->where('event_session_id', $source->sessionId)
                ->chunkById(200, function (iterable $facilities) use ($target, $locationIds): void {
                    foreach ($facilities as $facility) {
                        $replica = $facility->replicate(['id', 'created_at', 'updated_at']);
                        $replica->setAttribute('event_id', $target->eventId);
                        $replica->setAttribute('event_occurrence_id', $target->occurrenceId);
                        $replica->setAttribute('event_session_id', $target->sessionId);
                        $replica->setAttribute('event_location_id', $locationIds[(string) $facility->event_location_id] ?? null);
                        $replica->save();
                    }
                });
        });
    }
}
