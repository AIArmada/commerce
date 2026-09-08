<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Services;

use AIArmada\Engagement\Contracts\EngagementCounterService;
use AIArmada\Engagement\Events\BookmarkArchived;
use AIArmada\Engagement\Events\BookmarkCreated;
use AIArmada\Engagement\Events\BookmarkRemoved;
use AIArmada\Engagement\Events\FollowCreated;
use AIArmada\Engagement\Events\FollowMuted;
use AIArmada\Engagement\Events\FollowRemoved;
use AIArmada\Engagement\Events\FollowUnmuted;
use AIArmada\Engagement\Events\ReactionCreated;
use AIArmada\Engagement\Events\ReactionRemoved;
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
        return $this->countFollowersByIdentity($subject->getMorphClass(), (string) $subject->getKey());
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

    private function countFollowersByIdentity(string $subjectType, string $subjectId): int
    {
        return Follow::query()
            ->where('followable_type', $subjectType)
            ->where('followable_id', $subjectId)
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

        $this->recalculateResponses($subject);
        $this->recalculateReactions($subject);
    }

    /**
     * Compute base counters in one database round trip.
     *
     * Keyed response and reaction counters are reconciled separately so direct
     * writes cannot leave per-type values stale.
     *
     * @return array<string, int>
     */
    private function aggregateCounters(string $subjectType, string $subjectId): array
    {
        $followers = Follow::query()
            ->where('followable_type', $subjectType)
            ->where('followable_id', $subjectId)
            ->where('status', 'active')
            ->selectRaw('COUNT(*) as followers');
        $bookmarks = Bookmark::query()
            ->where('bookmarkable_type', $subjectType)
            ->where('bookmarkable_id', $subjectId)
            ->where('status', 'active')
            ->selectRaw('COUNT(*) as bookmarks');
        $responses = Response::query()
            ->where('respondable_type', $subjectType)
            ->where('respondable_id', $subjectId)
            ->where('status', 'active')
            ->selectRaw('COUNT(*) as responses');
        $reactions = Reaction::query()
            ->where('reactable_type', $subjectType)
            ->where('reactable_id', $subjectId)
            ->where('status', 'active')
            ->selectRaw('COUNT(*) as reactions');

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

    public function recalculateFollowers(mixed $subject): void
    {
        $this->recalculateFollowersByIdentity($subject->getMorphClass(), (string) $subject->getKey());
    }

    private function recalculateFollowersByIdentity(string $subjectType, string $subjectId): void
    {
        EngagementCounter::query()->updateOrCreate(
            [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'counter_type' => 'followers',
                'counter_key' => '',
            ],
            [
                'count_value' => $this->countFollowersByIdentity($subjectType, $subjectId),
                'recalculated_at' => CarbonImmutable::now(),
            ],
        );
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
        $counterKeys = $responseType === null
            ? $this->counterKeysForResponses($subjectType, $subjectId)
            : ['', $responseType];

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

    public function recalculateReactions(mixed $subject, ?string $reactionType = null): void
    {
        $this->recalculateReactionsByIdentity($subject->getMorphClass(), (string) $subject->getKey(), $reactionType);
    }

    private function recalculateReactionsByIdentity(string $subjectType, string $subjectId, ?string $reactionType = null): void
    {
        $counterKeys = $reactionType === null
            ? $this->counterKeysForReactions($subjectType, $subjectId)
            : ['', $reactionType];

        foreach ($counterKeys as $counterKey) {
            EngagementCounter::query()->updateOrCreate(
                [
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'counter_type' => 'reactions',
                    'counter_key' => $counterKey,
                ],
                [
                    'count_value' => $this->countReactionsByIdentity($subjectType, $subjectId, $counterKey === '' ? null : $counterKey),
                    'recalculated_at' => CarbonImmutable::now(),
                ],
            );
        }
    }

    private function countReactionsByIdentity(string $subjectType, string $subjectId, ?string $reactionType = null): int
    {
        $query = Reaction::query()
            ->where('reactable_type', $subjectType)
            ->where('reactable_id', $subjectId)
            ->where('status', 'active');

        if ($reactionType !== null) {
            $query->where('reaction_type', $reactionType);
        }

        return $query->count();
    }

    /**
     * @return array<int, string>
     */
    private function counterKeysForResponses(string $subjectType, string $subjectId): array
    {
        $activeKeys = Response::query()
            ->where('respondable_type', $subjectType)
            ->where('respondable_id', $subjectId)
            ->where('status', 'active')
            ->distinct()
            ->pluck('response_type')
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();

        $storedKeys = EngagementCounter::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('counter_type', 'responses')
            ->where('counter_key', '<>', '')
            ->pluck('counter_key')
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();

        return array_values(array_unique(array_merge([''], $activeKeys, $storedKeys)));
    }

    /**
     * @return array<int, string>
     */
    private function counterKeysForReactions(string $subjectType, string $subjectId): array
    {
        $activeKeys = Reaction::query()
            ->where('reactable_type', $subjectType)
            ->where('reactable_id', $subjectId)
            ->where('status', 'active')
            ->distinct()
            ->pluck('reaction_type')
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();

        $storedKeys = EngagementCounter::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('counter_type', 'reactions')
            ->where('counter_key', '<>', '')
            ->pluck('counter_key')
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();

        return array_values(array_unique(array_merge([''], $activeKeys, $storedKeys)));
    }

    public function onFollowCreated(FollowCreated $event): void
    {
        $this->recalculateFollowersByIdentity($event->follow->followable_type, (string) $event->follow->followable_id);
    }

    public function onFollowRemoved(FollowRemoved $event): void
    {
        $this->recalculateFollowersByIdentity($event->follow->followable_type, (string) $event->follow->followable_id);
    }

    public function onFollowMuted(FollowMuted $event): void
    {
        $this->recalculateFollowersByIdentity($event->follow->followable_type, (string) $event->follow->followable_id);
    }

    public function onFollowUnmuted(FollowUnmuted $event): void
    {
        $this->recalculateFollowersByIdentity($event->follow->followable_type, (string) $event->follow->followable_id);
    }

    public function onReactionCreated(ReactionCreated $event): void
    {
        $this->recalculateReactionsByIdentity(
            $event->reaction->reactable_type,
            (string) $event->reaction->reactable_id,
            $event->reaction->reaction_type,
        );
    }

    public function onReactionRemoved(ReactionRemoved $event): void
    {
        $this->recalculateReactionsByIdentity(
            $event->reaction->reactable_type,
            (string) $event->reaction->reactable_id,
            $event->reaction->reaction_type,
        );
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
