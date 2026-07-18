<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Services;

use AIArmada\Engagement\Contracts\EngagementCounterService;
use AIArmada\Engagement\Events\BookmarkArchived;
use AIArmada\Engagement\Events\BookmarkCreated;
use AIArmada\Engagement\Events\BookmarkRemoved;
use AIArmada\Engagement\Events\ResponseCancelled;
use AIArmada\Engagement\Events\ResponseChanged;
use AIArmada\Engagement\Events\ResponseCreated;
use AIArmada\Engagement\Models\Bookmark;
use AIArmada\Engagement\Models\EngagementCounter;
use AIArmada\Engagement\Models\Follow;
use AIArmada\Engagement\Models\Reaction;
use AIArmada\Engagement\Models\Response;
use Carbon\CarbonImmutable;

final class DefaultEngagementCounterService implements EngagementCounterService
{
    public function value(mixed $subject, string $counterType, string $counterKey = ''): int
    {
        $counter = EngagementCounter::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('counter_type', $counterType)
            ->where('counter_key', $counterKey)
            ->first();

        return $counter?->count_value ?? 0;
    }

    public function countFollowers(mixed $subject): int
    {
        return Follow::query()
            ->where('followable_type', $subject->getMorphClass())
            ->where('followable_id', $subject->getKey())
            ->where('status', 'active')
            ->count();
    }

    public function countBookmarks(mixed $subject): int
    {
        return Bookmark::query()
            ->where('bookmarkable_type', $subject->getMorphClass())
            ->where('bookmarkable_id', $subject->getKey())
            ->where('status', 'active')
            ->count();
    }

    public function countResponses(mixed $subject, ?string $responseType = null): int
    {
        $query = Response::query()
            ->where('respondable_type', $subject->getMorphClass())
            ->where('respondable_id', $subject->getKey())
            ->where('status', 'active');

        if ($responseType !== null) {
            $query->where('response_type', $responseType);
        }

        return $query->count();
    }

    public function countReactions(mixed $subject, ?string $reactionType = null): int
    {
        $query = Reaction::query()
            ->where('reactable_type', $subject->getMorphClass())
            ->where('reactable_id', $subject->getKey())
            ->where('status', 'active');

        if ($reactionType !== null) {
            $query->where('reaction_type', $reactionType);
        }

        return $query->count();
    }

    public function recalculate(mixed $subject): void
    {
        $subjectType = $subject->getMorphClass();
        $subjectId = $subject->getKey();

        $counters = [
            'followers' => $this->countFollowers($subject),
            'bookmarks' => $this->countBookmarks($subject),
            'responses' => $this->countResponses($subject),
            'reactions' => $this->countReactions($subject),
        ];

        foreach ($counters as $type => $count) {
            EngagementCounter::query()->updateOrCreate(
                [
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'counter_type' => $type,
                    'counter_key' => '',
                ],
                [
                    'count_value' => $count,
                    'recalculated_at' => CarbonImmutable::now(),
                ],
            );
        }
    }

    public function recalculateBookmarks(mixed $subject): void
    {
        $count = $this->countBookmarks($subject);

        EngagementCounter::query()->updateOrCreate(
            [
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'counter_type' => 'bookmarks',
                'counter_key' => '',
            ],
            [
                'count_value' => $count,
                'recalculated_at' => CarbonImmutable::now(),
            ],
        );
    }

    public function recalculateResponses(mixed $subject, ?string $responseType = null): void
    {
        $counterKeys = array_values(array_unique(array_filter(['', $responseType], static fn (?string $key): bool => $key !== null)));

        foreach ($counterKeys as $counterKey) {
            EngagementCounter::query()->updateOrCreate(
                [
                    'subject_type' => $subject->getMorphClass(),
                    'subject_id' => $subject->getKey(),
                    'counter_type' => 'responses',
                    'counter_key' => $counterKey,
                ],
                [
                    'count_value' => $this->countResponses($subject, $counterKey === '' ? null : $counterKey),
                    'recalculated_at' => CarbonImmutable::now(),
                ],
            );
        }
    }

    public function onBookmarkCreated(BookmarkCreated $event): void
    {
        $this->recalculateBookmarks($event->bookmark->bookmarkable);
    }

    public function onBookmarkRemoved(BookmarkRemoved $event): void
    {
        $this->recalculateBookmarks($event->bookmark->bookmarkable);
    }

    public function onBookmarkArchived(BookmarkArchived $event): void
    {
        $this->recalculateBookmarks($event->bookmark->bookmarkable);
    }

    public function onResponseCreated(ResponseCreated $event): void
    {
        $this->recalculateResponses($event->response->respondable, $event->response->response_type);
    }

    public function onResponseChanged(ResponseChanged $event): void
    {
        $this->recalculateResponses($event->response->respondable, $event->previousType);
        $this->recalculateResponses($event->response->respondable, $event->response->response_type);
    }

    public function onResponseCancelled(ResponseCancelled $event): void
    {
        $this->recalculateResponses($event->response->respondable, $event->response->response_type);
    }
}
