<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Taxonomy\Actions;

use AIArmada\Events\Features\Taxonomy\Models\EventClassification;
use Illuminate\Database\Eloquent\Builder;

final class DemotePrimaryClassification
{
    public function handle(EventClassification $classification): void
    {
        if (! $classification->is_primary) {
            return;
        }

        EventClassification::query()
            ->where('event_id', $classification->event_id)
            ->when(
                $classification->event_occurrence_id === null,
                fn (Builder $query): Builder => $query->whereNull('event_occurrence_id'),
                fn (Builder $query): Builder => $query->where('event_occurrence_id', $classification->event_occurrence_id),
            )
            ->when(
                $classification->event_session_id === null,
                fn (Builder $query): Builder => $query->whereNull('event_session_id'),
                fn (Builder $query): Builder => $query->where('event_session_id', $classification->event_session_id),
            )
            ->whereKeyNot($classification->getKey())
            ->update(['is_primary' => false]);
    }
}
