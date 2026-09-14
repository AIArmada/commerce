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
    public const int DEFAULT_LIMIT = 100;

    public const int MAX_LIMIT = 500;

    public function findPublished(int $limit = self::DEFAULT_LIMIT): Collection
    {
        $eventClass = ModelResolver::eventClass();

        return $eventClass::published()
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($this->clampLimit($limit))
            ->get();
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
            ->limit($this->clampLimit($limit))
            ->get();
    }

    public function findByOwner(Model $owner, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $eventClass = ModelResolver::eventClass();

        return $eventClass::forOwner($owner)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($this->clampLimit($limit))
            ->get();
    }

    /**
     * Event slugs are not unique, so the earliest created match wins.
     */
    public function findBySlug(string $slug): ?Event
    {
        $eventClass = ModelResolver::eventClass();

        return $eventClass::where('slug', $slug)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    private function clampLimit(int $limit): int
    {
        return max(1, min($limit, self::MAX_LIMIT));
    }
}
