<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Support\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class EventQueryService
{
    public function findPublished(): Collection
    {
        $eventClass = ModelResolver::eventClass();

        return $eventClass::published()->get();
    }

    public function findUpcoming(int $limit = 10): Collection
    {
        $eventClass = ModelResolver::eventClass();
        $eventTable = (new $eventClass)->getTable();
        $occurrenceTable = (new EventOccurrence)->getTable();
        $nextOccurrenceSubquery = EventOccurrence::query()
            ->select('starts_at')
            ->whereColumn("{$occurrenceTable}.event_id", "{$eventTable}.id")
            ->orderBy('starts_at')
            ->limit(1);

        return $eventClass::published()
            ->whereHas('occurrences', function (Builder $query): void {
                $query->where('starts_at', '>=', CarbonImmutable::now());
            })
            ->orderBy($nextOccurrenceSubquery)
            ->limit($limit)
            ->get();
    }

    public function findByOwner(Model $owner): Collection
    {
        $eventClass = ModelResolver::eventClass();

        return $eventClass::forOwner($owner)->get();
    }

    public function findBySlug(string $slug): ?Event
    {
        $eventClass = ModelResolver::eventClass();

        return $eventClass::where('slug', $slug)->first();
    }
}
