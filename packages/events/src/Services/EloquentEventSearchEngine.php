<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\Events\Contracts\EventSearchEngine;
use AIArmada\Events\Support\ModelResolver;
use Illuminate\Database\Eloquent\Collection;

final class EloquentEventSearchEngine implements EventSearchEngine
{
    private const array SORTABLE_FIELDS = [
        'created_at',
        'title',
        'published_at',
        'updated_at',
    ];

    private const int DEFAULT_LIMIT = 25;

    private const int MAX_LIMIT = 100;

    public function search(array $criteria): Collection
    {
        $eventClass = ModelResolver::eventClass();
        $query = $eventClass::query();

        if (! empty($criteria['status'])) {
            $query->where('status', $criteria['status']);
        }

        if (! empty($criteria['visibility'])) {
            $query->where('visibility', $criteria['visibility']);
        } else {
            $query->where('visibility', '!=', 'hidden');
        }

        if (! empty($criteria['type'])) {
            $query->where('type', $criteria['type']);
        }

        if (! empty($criteria['delivery_mode'])) {
            $query->where('delivery_mode', $criteria['delivery_mode']);
        }

        if (! empty($criteria['search'])) {
            $pattern = LikeSearch::contains((string) $criteria['search']);

            $query->where(function ($q) use ($pattern): void {
                LikeSearch::whereLike($q, 'title', $pattern);
                LikeSearch::orWhereLike($q, 'summary', $pattern);
                LikeSearch::orWhereLike($q, 'description', $pattern);
            });
        }

        if (! empty($criteria['owner_type']) && ! empty($criteria['owner_id'])) {
            $query->where('owner_type', $criteria['owner_type'])
                ->where('owner_id', $criteria['owner_id']);
        }

        if (! empty($criteria['starts_after'])) {
            $query->whereHas('occurrences', function ($q) use ($criteria): void {
                $q->where('starts_at', '>=', $criteria['starts_after']);
            });
        }

        if (! empty($criteria['ends_before'])) {
            $query->whereHas('occurrences', function ($q) use ($criteria): void {
                $q->where('ends_at', '<=', $criteria['ends_before']);
            });
        }

        $sortField = in_array($criteria['sort'] ?? null, self::SORTABLE_FIELDS, true)
            ? $criteria['sort']
            : 'created_at';
        $sortDir = in_array(mb_strtolower((string) ($criteria['sort_dir'] ?? 'desc')), ['asc', 'desc'], true)
            ? mb_strtolower((string) ($criteria['sort_dir'] ?? 'desc'))
            : 'desc';

        $query->orderBy($sortField, $sortDir);

        $limit = (int) ($criteria['limit'] ?? self::DEFAULT_LIMIT);
        $query->limit(max(1, min($limit, self::MAX_LIMIT)));

        return $query->get();
    }
}
