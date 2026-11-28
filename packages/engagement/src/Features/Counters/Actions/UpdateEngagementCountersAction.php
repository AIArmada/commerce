<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Counters\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
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
use AIArmada\Engagement\Features\Counters\Api\ReadsEngagementCounters;
use AIArmada\Engagement\Features\Counters\Models\EngagementCounter;
use AIArmada\Engagement\Features\Interactions\Api\ReadsInteractionCounts;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

final readonly class UpdateEngagementCountersAction implements ReadsEngagementCounters
{
    public function __construct(private ReadsInteractionCounts $interactions) {}

    public function count(string $subjectType, string $subjectId, string $counterType, ?string $counterKey = null): int
    {
        return match ($counterType) {
            'followers' => $this->interactions->countFollowersByIdentity($subjectType, $subjectId),
            'bookmarks' => $this->interactions->countBookmarksByIdentity($subjectType, $subjectId),
            'responses' => $this->interactions->countResponsesByIdentity($subjectType, $subjectId, $counterKey),
            'reactions' => $this->interactions->countReactionsByIdentity($subjectType, $subjectId, $counterKey),
            default => $this->valueByIdentity($subjectType, $subjectId, $counterType, $counterKey ?? ''),
        };
    }

    public function value(mixed $subject, string $counterType, string $counterKey = ''): int
    {
        return $this->valueByIdentity($subject->getMorphClass(), (string) $subject->getKey(), $counterType, $counterKey);
    }

    private function valueByIdentity(string $subjectType, string $subjectId, string $counterType, string $counterKey): int
    {
        $counter = EngagementCounter::query()
            ->forOwner()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('counter_type', $counterType)
            ->where('counter_key', $counterKey)
            ->first();

        return $counter?->count_value ?? 0;
    }

    public function countFollowers(mixed $subject): int
    {
        return $this->interactions->countFollowersByIdentity($subject->getMorphClass(), (string) $subject->getKey());
    }

    public function countBookmarks(mixed $subject): int
    {
        return $this->interactions->countBookmarksByIdentity($subject->getMorphClass(), (string) $subject->getKey());
    }

    public function countResponses(mixed $subject, ?string $responseType = null): int
    {
        return $this->interactions->countResponsesByIdentity(
            $subject->getMorphClass(),
            (string) $subject->getKey(),
            $responseType,
        );
    }

    public function countReactions(mixed $subject, ?string $reactionType = null): int
    {
        return $this->interactions->countReactionsByIdentity($subject->getMorphClass(), (string) $subject->getKey(), $reactionType);
    }

    public function recalculate(mixed $subject): void
    {
        $subjectType = $subject->getMorphClass();
        $subjectId = (string) $subject->getKey();

        $this->withLockedCounters($subjectType, $subjectId, ['bookmarks', 'followers'], function () use ($subjectType, $subjectId): void {
            $counters = $this->interactions->aggregateCounters($subjectType, $subjectId);

            foreach ($counters as $type => $count) {
                EngagementCounter::query()->forOwner()->updateOrCreate(
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
        });

        $this->recalculateResponses($subject);
        $this->recalculateReactions($subject);
    }

    public function recalculateBookmarks(mixed $subject): void
    {
        $type = $subject->getMorphClass();
        $id = (string) $subject->getKey();
        $this->withLockedCounters($type, $id, ['bookmarks'], fn () => $this->recalculateBookmarksByIdentity($type, $id));
    }

    public function recalculateFollowers(mixed $subject): void
    {
        $type = $subject->getMorphClass();
        $id = (string) $subject->getKey();
        $this->withLockedCounters($type, $id, ['followers'], fn () => $this->recalculateFollowersByIdentity($type, $id));
    }

    private function recalculateFollowersByIdentity(string $subjectType, string $subjectId): void
    {
        EngagementCounter::query()->forOwner()->updateOrCreate(
            [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'counter_type' => 'followers',
                'counter_key' => '',
            ],
            [
                'count_value' => $this->interactions->countFollowersByIdentity($subjectType, $subjectId),
                'recalculated_at' => CarbonImmutable::now(),
            ],
        );
    }

    private function recalculateBookmarksByIdentity(string $subjectType, string $subjectId): void
    {
        $count = $this->interactions->countBookmarksByIdentity($subjectType, $subjectId);

        EngagementCounter::query()->forOwner()->updateOrCreate(
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
        $type = $subject->getMorphClass();
        $id = (string) $subject->getKey();
        $this->withLockedCounters($type, $id, ['responses'], fn () => $this->recalculateResponsesByIdentity($type, $id, $responseType));
    }

    private function recalculateResponsesByIdentity(string $subjectType, string $subjectId, ?string $responseType = null): void
    {
        if ($responseType !== null) {
            foreach (['', $responseType] as $counterKey) {
                EngagementCounter::query()->forOwner()->updateOrCreate(
                    [
                        'subject_type' => $subjectType,
                        'subject_id' => $subjectId,
                        'counter_type' => 'responses',
                        'counter_key' => $counterKey,
                    ],
                    [
                        'count_value' => $this->interactions->countResponsesByIdentity($subjectType, $subjectId, $counterKey === '' ? null : $counterKey),
                        'recalculated_at' => CarbonImmutable::now(),
                    ],
                );
            }

            return;
        }

        $counts = $this->interactions->groupedResponseCounts($subjectType, $subjectId);
        $now = CarbonImmutable::now();

        EngagementCounter::query()->forOwner()->updateOrCreate(
            [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'counter_type' => 'responses',
                'counter_key' => '',
            ],
            [
                'count_value' => array_sum($counts),
                'recalculated_at' => $now,
            ],
        );

        foreach ($this->responseCounterKeys($subjectType, $subjectId, $counts) as $counterKey) {
            EngagementCounter::query()->forOwner()->updateOrCreate(
                [
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'counter_type' => 'responses',
                    'counter_key' => $counterKey,
                ],
                [
                    'count_value' => $counts[$counterKey] ?? 0,
                    'recalculated_at' => $now,
                ],
            );
        }
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<int, string>
     */
    private function responseCounterKeys(string $subjectType, string $subjectId, array $counts): array
    {
        $storedKeys = EngagementCounter::query()->forOwner()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('counter_type', 'responses')
            ->where('counter_key', '<>', '')
            ->pluck('counter_key')
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();

        return array_values(array_unique(array_merge(array_keys($counts), $storedKeys)));
    }

    public function recalculateReactions(mixed $subject, ?string $reactionType = null): void
    {
        $type = $subject->getMorphClass();
        $id = (string) $subject->getKey();
        $this->withLockedCounters($type, $id, ['reactions'], fn () => $this->recalculateReactionsByIdentity($type, $id, $reactionType));
    }

    private function recalculateReactionsByIdentity(string $subjectType, string $subjectId, ?string $reactionType = null): void
    {
        if ($reactionType !== null) {
            foreach (['', $reactionType] as $counterKey) {
                EngagementCounter::query()->forOwner()->updateOrCreate(
                    [
                        'subject_type' => $subjectType,
                        'subject_id' => $subjectId,
                        'counter_type' => 'reactions',
                        'counter_key' => $counterKey,
                    ],
                    [
                        'count_value' => $this->interactions->countReactionsByIdentity($subjectType, $subjectId, $counterKey === '' ? null : $counterKey),
                        'recalculated_at' => CarbonImmutable::now(),
                    ],
                );
            }

            return;
        }

        $counts = $this->interactions->groupedReactionCounts($subjectType, $subjectId);
        $now = CarbonImmutable::now();

        EngagementCounter::query()->forOwner()->updateOrCreate(
            [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'counter_type' => 'reactions',
                'counter_key' => '',
            ],
            [
                'count_value' => array_sum($counts),
                'recalculated_at' => $now,
            ],
        );

        foreach ($this->reactionCounterKeys($subjectType, $subjectId, $counts) as $counterKey) {
            EngagementCounter::query()->forOwner()->updateOrCreate(
                [
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'counter_type' => 'reactions',
                    'counter_key' => $counterKey,
                ],
                [
                    'count_value' => $counts[$counterKey] ?? 0,
                    'recalculated_at' => $now,
                ],
            );
        }
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<int, string>
     */
    private function reactionCounterKeys(string $subjectType, string $subjectId, array $counts): array
    {
        $storedKeys = EngagementCounter::query()->forOwner()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('counter_type', 'reactions')
            ->where('counter_key', '<>', '')
            ->pluck('counter_key')
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();

        return array_values(array_unique(array_merge(array_keys($counts), $storedKeys)));
    }

    /** @param list<string> $counterTypes */
    private function withLockedCounters(string $subjectType, string $subjectId, array $counterTypes, callable $write): void
    {
        (new EngagementCounter)->getConnection()->transaction(function () use ($subjectType, $subjectId, $counterTypes, $write): void {
            foreach ($counterTypes as $counterType) {
                $this->lockAggregateCounter($subjectType, $subjectId, $counterType);
            }

            $write();
        }, attempts: 3);
    }

    private function lockAggregateCounter(string $subjectType, string $subjectId, string $counterType): void
    {
        $attributes = [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'counter_type' => $counterType,
            'counter_key' => '',
        ];

        $counter = EngagementCounter::query()->forOwner()->where($attributes)->lockForUpdate()->first();

        if ($counter instanceof EngagementCounter) {
            return;
        }

        try {
            (new EngagementCounter)->getConnection()->transaction(static fn (): EngagementCounter => EngagementCounter::query()->create([
                ...$attributes,
                'count_value' => 0,
            ]));
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }
        }

        EngagementCounter::query()->forOwner()->where($attributes)->lockForUpdate()->firstOrFail();
    }

    /**
     * Apply one lifecycle transition to the cached counters without a full
     * recount. Events fire inside the originating write transaction, so the
     * adjustment stays synchronous with the write. Direct model writes and
     * imports bypass these events; `recalculate()` (via
     * `engagement:reconcile-counters`) remains the repair path for drift.
     */
    private function adjustCounter(string $subjectType, string $subjectId, string $counterType, string $counterKey, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        try {
            (new EngagementCounter)->getConnection()->transaction(function () use ($subjectType, $subjectId, $counterType, $counterKey, $delta): void {
                $this->adjustLockedCounter($subjectType, $subjectId, $counterType, $counterKey, $delta);
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            (new EngagementCounter)->getConnection()->transaction(function () use ($subjectType, $subjectId, $counterType, $counterKey, $delta): void {
                $this->adjustLockedCounter($subjectType, $subjectId, $counterType, $counterKey, $delta);
            });
        }
    }

    private function adjustLockedCounter(string $subjectType, string $subjectId, string $counterType, string $counterKey, int $delta): void
    {
        $counter = EngagementCounter::query()->forOwner()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('counter_type', $counterType)
            ->where('counter_key', $counterKey)
            ->lockForUpdate()
            ->first();

        if (! $counter instanceof EngagementCounter) {
            if ($delta < 0 && $counterKey !== '') {
                return;
            }

            $counter = EngagementCounter::query()->create([
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'counter_type' => $counterType,
                'counter_key' => $counterKey,
                'count_value' => 0,
            ]);
        }

        $counter->update([
            'count_value' => max(0, (int) $counter->count_value + $delta),
            'recalculated_at' => CarbonImmutable::now(),
        ]);
    }

    private function inEventOwner(?string $ownerType, ?string $ownerId, callable $callback): void
    {
        if (! EngagementCounter::ownerScopeConfig()->enabled) {
            $callback();

            return;
        }

        $owner = OwnerTupleParser::fromTypeAndId($ownerType, $ownerId)->toOwnerModelOrFail();

        OwnerContext::withOwner($owner, $callback);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }

    public function onFollowCreated(FollowCreated $event): void
    {
        $this->inEventOwner($event->ownerType, $event->ownerId, fn () => $this->adjustCounter($event->subjectType, $event->subjectId, 'followers', '', 1));
    }

    public function onFollowRemoved(FollowRemoved $event): void
    {
        $this->inEventOwner($event->ownerType, $event->ownerId, fn () => $this->adjustCounter($event->subjectType, $event->subjectId, 'followers', '', -1));
    }

    public function onFollowMuted(FollowMuted $event): void
    {
        $this->inEventOwner($event->ownerType, $event->ownerId, fn () => $this->adjustCounter($event->subjectType, $event->subjectId, 'followers', '', -1));
    }

    public function onFollowUnmuted(FollowUnmuted $event): void
    {
        $this->inEventOwner($event->ownerType, $event->ownerId, fn () => $this->adjustCounter($event->subjectType, $event->subjectId, 'followers', '', 1));
    }

    public function onReactionCreated(ReactionCreated $event): void
    {
        $this->inEventOwner($event->ownerType, $event->ownerId, function () use ($event): void {
            $this->withLockedCounters($event->subjectType, $event->subjectId, ['reactions'], function () use ($event): void {
                $this->adjustCounter($event->subjectType, $event->subjectId, 'reactions', '', 1);
                $this->adjustCounter($event->subjectType, $event->subjectId, 'reactions', $event->reactionType, 1);
            });
        });
    }

    public function onReactionRemoved(ReactionRemoved $event): void
    {
        $this->inEventOwner($event->ownerType, $event->ownerId, function () use ($event): void {
            $this->withLockedCounters($event->subjectType, $event->subjectId, ['reactions'], function () use ($event): void {
                $this->adjustCounter($event->subjectType, $event->subjectId, 'reactions', '', -1);
                $this->adjustCounter($event->subjectType, $event->subjectId, 'reactions', $event->reactionType, -1);
            });
        });
    }

    public function onBookmarkCreated(BookmarkCreated $event): void
    {
        $this->inEventOwner($event->ownerType, $event->ownerId, fn () => $this->adjustCounter($event->subjectType, $event->subjectId, 'bookmarks', '', 1));
    }

    public function onBookmarkRemoved(BookmarkRemoved $event): void
    {
        $this->inEventOwner($event->ownerType, $event->ownerId, fn () => $this->adjustCounter($event->subjectType, $event->subjectId, 'bookmarks', '', -1));
    }

    public function onBookmarkArchived(BookmarkArchived $event): void
    {
        $this->inEventOwner($event->ownerType, $event->ownerId, fn () => $this->adjustCounter($event->subjectType, $event->subjectId, 'bookmarks', '', -1));
    }

    public function onResponseCreated(ResponseCreated $event): void
    {
        $this->inEventOwner($event->ownerType, $event->ownerId, function () use ($event): void {
            $this->withLockedCounters($event->subjectType, $event->subjectId, ['responses'], function () use ($event): void {
                $this->adjustCounter($event->subjectType, $event->subjectId, 'responses', '', 1);
                $this->adjustCounter($event->subjectType, $event->subjectId, 'responses', $event->responseType, 1);
            });
        });
    }

    public function onResponseChanged(ResponseChanged $event): void
    {
        if ($event->previousType === $event->responseType) {
            return;
        }

        $this->inEventOwner($event->ownerType, $event->ownerId, function () use ($event): void {
            $this->withLockedCounters($event->subjectType, $event->subjectId, ['responses'], function () use ($event): void {
                $this->adjustCounter($event->subjectType, $event->subjectId, 'responses', $event->previousType, -1);
                $this->adjustCounter($event->subjectType, $event->subjectId, 'responses', $event->responseType, 1);
            });
        });
    }

    public function onResponseCancelled(ResponseCancelled $event): void
    {
        $this->inEventOwner($event->ownerType, $event->ownerId, function () use ($event): void {
            $this->withLockedCounters($event->subjectType, $event->subjectId, ['responses'], function () use ($event): void {
                $this->adjustCounter($event->subjectType, $event->subjectId, 'responses', '', -1);
                $this->adjustCounter($event->subjectType, $event->subjectId, 'responses', $event->responseType, -1);
            });
        });
    }
}
