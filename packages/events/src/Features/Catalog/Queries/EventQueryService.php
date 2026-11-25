<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Queries;

use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\Events\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class EventQueryService
{
    public const int DEFAULT_LIMIT = 100;

    public const int MAX_LIMIT = 500;

    /**
     * @return Collection<int, Model>
     */
    public function findPublished(int $limit = self::DEFAULT_LIMIT): Collection
    {
        $eventClass = ModelResolver::eventClass();

        return $eventClass::query()
            ->where('status', 'published')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($this->clampLimit($limit))
            ->get();
    }

    /**
     * @return Collection<int, Model>
     */
    public function findUpcoming(int $limit = 10): Collection
    {
        $eventClass = ModelResolver::eventClass();
        $occurrenceClass = ModelResolver::occurrenceClass();

        $eventTable = (new $eventClass)->getTable();
        $occurrenceTable = (new $occurrenceClass)->getTable();
        $now = CarbonImmutable::now();

        $nextOccurrenceSubquery = $occurrenceClass::query()
            ->select('starts_at')
            ->whereColumn("{$occurrenceTable}.event_id", "{$eventTable}.id")
            ->where("{$occurrenceTable}.starts_at", '>=', $now)
            ->orderBy("{$occurrenceTable}.starts_at")
            ->limit(1);

        return $eventClass::query()
            ->where('status', 'published')
            ->whereHas('occurrences', function (Builder $query) use ($now): void {
                $query->where('starts_at', '>=', $now);
            })
            ->orderBy($nextOccurrenceSubquery)
            ->orderBy("{$eventTable}.created_at")
            ->orderBy("{$eventTable}.id")
            ->limit($this->clampLimit($limit))
            ->get();
    }

    /**
     * @return Collection<int, Model>
     */
    public function findByOwner(Model $owner, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $eventClass = ModelResolver::eventClass();
        $config = OwnerScopeConfig::fromConfig('events.features.owner', true);

        $query = OwnerQuery::applyToEloquentBuilder(
            $eventClass::query(),
            $owner,
            $config->includeGlobal,
            $config->ownerTypeColumn,
            $config->ownerIdColumn,
        );

        return $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($this->clampLimit($limit))
            ->get();
    }

    /**
     * Event slugs are not unique, so the earliest created match wins.
     */
    public function findBySlug(string $slug): ?Model
    {
        $eventClass = ModelResolver::eventClass();

        return $eventClass::query()
            ->where('slug', $slug)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    private function clampLimit(int $limit): int
    {
        return max(1, min($limit, self::MAX_LIMIT));
    }
}
