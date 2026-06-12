<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventSearchEngine;
use AIArmada\Events\Models\Event;
use Illuminate\Database\Eloquent\Collection;

final class EloquentEventSearchEngine implements EventSearchEngine
{
    public function search(array $criteria): Collection
    {
        $query = Event::query();

        if (! empty($criteria['status'])) {
            $query->where('status', $criteria['status']);
        }

        if (! empty($criteria['visibility'])) {
            $query->where('visibility', $criteria['visibility']);
        }

        if (! empty($criteria['type'])) {
            $query->where('type', $criteria['type']);
        }

        if (! empty($criteria['delivery_mode'])) {
            $query->where('delivery_mode', $criteria['delivery_mode']);
        }

        if (! empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
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

        $sortField = $criteria['sort'] ?? 'created_at';
        $sortDir = $criteria['sort_dir'] ?? 'desc';

        $query->orderBy($sortField, $sortDir);

        if (! empty($criteria['limit'])) {
            $query->limit((int) $criteria['limit']);
        }

        return $query->get();
    }
}
