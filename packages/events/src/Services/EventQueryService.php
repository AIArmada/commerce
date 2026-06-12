<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class EventQueryService
{
    public function findPublished(): Collection
    {
        return Event::published()->get();
    }

    public function findUpcoming(int $limit = 10): Collection
    {
        $eventTable = (new Event)->getTable();
        $occurrenceTable = (new EventOccurrence)->getTable();
        $nextOccurrenceSubquery = EventOccurrence::query()
            ->select('starts_at')
            ->whereColumn("{$occurrenceTable}.event_id", "{$eventTable}.id")
            ->orderBy('starts_at')
            ->limit(1);

        return Event::published()
            ->whereHas('occurrences', function (Builder $query): void {
                $query->where('starts_at', '>=', CarbonImmutable::now());
            })
            ->orderBy($nextOccurrenceSubquery)
            ->limit($limit)
            ->get();
    }

    public function findByOwner(Model $owner): Collection
    {
        return Event::forOwner($owner)->get();
    }

    public function findBySlug(string $slug): ?Event
    {
        return Event::where('slug', $slug)->first();
    }
}
