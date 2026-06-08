<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventSearchEngine;
use AIArmada\Events\Data\EventSearchCardData;
use AIArmada\Events\Data\EventSearchCriteria;
use AIArmada\Events\Data\EventSearchResultData;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Models\Event;
use Illuminate\Database\Eloquent\Builder;

final class EloquentEventSearchEngine implements EventSearchEngine
{
    public function search(EventSearchCriteria $criteria): EventSearchResultData
    {
        $query = Event::query()->forOwner(OwnerContext::resolve(), $criteria->includeGlobal);

        $this->applyTermFilter($query, $criteria->term);
        $this->applyEnumFilters($query, $criteria);
        $this->applyRelationFilters($query, $criteria);
        $this->applyDateFilters($query, $criteria);
        $this->applySorting($query, $criteria);

        $total = (clone $query)->count();

        $items = $query
            ->forPage(max(1, $criteria->page), max(1, $criteria->perPage))
            ->get()
            ->map(static fn (Event $event): EventSearchCardData => EventSearchCardData::fromEvent($event))
            ->values()
            ->all();

        return EventSearchResultData::fromCards($items, $total, max(1, $criteria->page), max(1, $criteria->perPage));
    }

    private function applyTermFilter(Builder $query, ?string $term): void
    {
        $term = is_string($term) ? mb_trim($term) : null;

        if ($term === null || $term === '') {
            return;
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

        $query->where(static function (Builder $builder) use ($like): void {
            $builder
                ->where('name', 'like', $like)
                ->orWhere('slug', 'like', $like)
                ->orWhere('summary', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('search_keywords', 'like', $like);
        });
    }

    private function applyEnumFilters(Builder $query, EventSearchCriteria $criteria): void
    {
        if ($criteria->statuses !== []) {
            $query->whereIn('status', $criteria->statuses);
        }

        if ($criteria->moderationStatuses !== []) {
            $query->whereIn('moderation_status', array_map(
                static fn (string $status): string => EventModerationStatus::tryFrom($status)?->value ?? $status,
                $criteria->moderationStatuses,
            ));
        }

        if ($criteria->visibilities !== []) {
            $query->whereIn('visibility', $criteria->visibilities);
        }

        if ($criteria->structures !== []) {
            $query->whereIn('structure', $criteria->structures);
        }
    }

    private function applyRelationFilters(Builder $query, EventSearchCriteria $criteria): void
    {
        if ($criteria->classificationGroups !== []) {
            $query->whereHas('classifications', static function (Builder $builder) use ($criteria): void {
                $builder->whereIn('group_key', $criteria->classificationGroups);
            });
        }

        if ($criteria->assetRoles !== []) {
            $query->whereHas('assets', static function (Builder $builder) use ($criteria): void {
                $builder->whereIn('role_key', $criteria->assetRoles);
            });
        }

        if ($criteria->referenceKinds !== []) {
            $query->whereHas('references', static function (Builder $builder) use ($criteria): void {
                $builder->whereIn('reference_kind', $criteria->referenceKinds);
            });
        }
    }

    private function applyDateFilters(Builder $query, EventSearchCriteria $criteria): void
    {
        if ($criteria->publishedAfter !== null) {
            $query->where('published_at', '>=', $criteria->publishedAfter);
        }

        if ($criteria->publishedBefore !== null) {
            $query->where('published_at', '<=', $criteria->publishedBefore);
        }
    }

    private function applySorting(Builder $query, EventSearchCriteria $criteria): void
    {
        $direction = mb_strtolower($criteria->direction) === 'asc' ? 'asc' : 'desc';
        $sort = $this->sortColumn($criteria->sort);

        if ($sort !== null) {
            $query->orderBy($sort, $direction);

            if ($sort !== 'name') {
                $query->orderBy('name', 'asc');
            }

            return;
        }

        $query
            ->orderBy('published_at', 'desc')
            ->orderBy('name', 'asc');
    }

    private function sortColumn(?string $sort): ?string
    {
        $sort = is_string($sort) ? mb_trim($sort) : null;

        if ($sort === null || $sort === '') {
            return null;
        }

        return match ($sort) {
            'name', 'published_at', 'public_starts_at', 'public_ends_at', 'created_at' => $sort,
            default => null,
        };
    }
}
