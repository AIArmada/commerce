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
use Illuminate\Support\Facades\DB;

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
        return $this->countBookmarksByIdentity($subject->getMorphClass(), (string) $subject->getKey());
    }

    public function countResponses(mixed $subject, ?string $responseType = null): int
    {
        return $this->countResponsesByIdentity(
            $subject->getMorphClass(),
            (string) $subject->getKey(),
            $responseType,
        );
    }

    private function countBookmarksByIdentity(string $subjectType, string $subjectId): int
    {
        return Bookmark::query()
            ->where('bookmarkable_type', $subjectType)
            ->where('bookmarkable_id', $subjectId)
            ->where('status', 'active')
            ->count();
    }

    private function countResponsesByIdentity(string $subjectType, string $subjectId, ?string $responseType = null): int
    {
        $query = Response::query()
            ->where('respondable_type', $subjectType)
            ->where('respondable_id', $subjectId)
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

        $counters = $this->aggregateCounters($subjectType, (string) $subjectId);

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

    /**
     * Compute all base counters in one database round trip.
     *
     * The individual public count methods remain available for callers that
     * request one counter, while reconciliation avoids four identical scans.
     *
     * @return array<string, int>
     */
    private function aggregateCounters(string $subjectType, string $subjectId): array
    {
        $followers = Follow::query()
            ->where('followable_type', $subjectType)
            ->where('followable_id', $subjectId)
            ->where('status', 'active')
            ->selectRaw('COUNT(*) as followers')
            ->selectRaw('COUNT(*)');
        $bookmarks = Bookmark::query()
            ->where('bookmarkable_type', $subjectType)
            ->where('bookmarkable_id', $subjectId)
            ->where('status', 'active')
            ->selectRaw('COUNT(*) as bookmarks')
            ->selectRaw('COUNT(*)');
        $responses = Response::query()
            ->where('respondable_type', $subjectType)
            ->where('respondable_id', $subjectId)
            ->where('status', 'active')
            ->selectRaw('COUNT(*) as responses')
            ->selectRaw('COUNT(*)');
        $reactions = Reaction::query()
            ->where('reactable_type', $subjectType)
            ->where('reactable_id', $subjectId)
            ->where('status', 'active')
            ->selectRaw('COUNT(*) as reactions')
            ->selectRaw('COUNT(*)');

        $totals = DB::query()
            ->selectSub($followers, 'followers')
            ->selectSub($bookmarks, 'bookmarks')
            ->selectSub($responses, 'responses')
            ->selectSub($reactions, 'reactions')
            ->first();

        return [
            'followers' => (int) ($totals->followers ?? 0),
            'bookmarks' => (int) ($totals->bookmarks ?? 0),
            'responses' => (int) ($totals->responses ?? 0),
            'reactions' => (int) ($totals->reactions ?? 0),
        ];
    }

    public function recalculateBookmarks(mixed $subject): void
    {
        $this->recalculateBookmarksByIdentity($subject->getMorphClass(), (string) $subject->getKey());
    }

    private function recalculateBookmarksByIdentity(string $subjectType, string $subjectId): void
    {
        $count = $this->countBookmarksByIdentity($subjectType, $subjectId);

        EngagementCounter::query()->updateOrCreate(
            [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
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
        $this->recalculateResponsesByIdentity($subject->getMorphClass(), (string) $subject->getKey(), $responseType);
    }

    private function recalculateResponsesByIdentity(string $subjectType, string $subjectId, ?string $responseType = null): void
    {
        $counterKeys = array_values(array_unique(array_filter(['', $responseType], static fn (?string $key): bool => $key !== null)));

        foreach ($counterKeys as $counterKey) {
            EngagementCounter::query()->updateOrCreate(
                [
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'counter_type' => 'responses',
                    'counter_key' => $counterKey,
                ],
                [
                    'count_value' => $this->countResponsesByIdentity($subjectType, $subjectId, $counterKey === '' ? null : $counterKey),
                    'recalculated_at' => CarbonImmutable::now(),
                ],
            );
        }
    }

    public function onBookmarkCreated(BookmarkCreated $event): void
    {
        $this->recalculateBookmarksByIdentity($event->bookmark->bookmarkable_type, (string) $event->bookmark->bookmarkable_id);
    }

    public function onBookmarkRemoved(BookmarkRemoved $event): void
    {
        $this->recalculateBookmarksByIdentity($event->bookmark->bookmarkable_type, (string) $event->bookmark->bookmarkable_id);
    }

    public function onBookmarkArchived(BookmarkArchived $event): void
    {
        $this->recalculateBookmarksByIdentity($event->bookmark->bookmarkable_type, (string) $event->bookmark->bookmarkable_id);
    }

    public function onResponseCreated(ResponseCreated $event): void
    {
        $this->recalculateResponsesByIdentity(
            $event->response->respondable_type,
            (string) $event->response->respondable_id,
            $event->response->response_type,
        );
    }

    public function onResponseChanged(ResponseChanged $event): void
    {
        $this->recalculateResponsesByIdentity(
            $event->response->respondable_type,
            (string) $event->response->respondable_id,
            $event->previousType,
        );
        $this->recalculateResponsesByIdentity(
            $event->response->respondable_type,
            (string) $event->response->respondable_id,
            $event->response->response_type,
        );
    }

    public function onResponseCancelled(ResponseCancelled $event): void
    {
        $this->recalculateResponsesByIdentity(
            $event->response->respondable_type,
            (string) $event->response->respondable_id,
            $event->response->response_type,
        );
    }
}
