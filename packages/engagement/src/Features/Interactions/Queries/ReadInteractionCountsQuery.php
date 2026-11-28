<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Queries;

use AIArmada\Engagement\Features\Interactions\Api\ReadsInteractionCounts;
use AIArmada\Engagement\Features\Interactions\Models\Bookmark;
use AIArmada\Engagement\Features\Interactions\Models\Follow;
use AIArmada\Engagement\Features\Interactions\Models\Reaction;
use AIArmada\Engagement\Features\Interactions\Models\Response;

final readonly class ReadInteractionCountsQuery implements ReadsInteractionCounts
{
    public function countBookmarksByIdentity(string $subjectType, string $subjectId): int
    {
        return Bookmark::query()->forOwner()
            ->where('bookmarkable_type', $subjectType)
            ->where('bookmarkable_id', $subjectId)
            ->where('status', 'active')
            ->count();
    }

    public function countFollowersByIdentity(string $subjectType, string $subjectId): int
    {
        return Follow::query()->forOwner()
            ->where('followable_type', $subjectType)
            ->where('followable_id', $subjectId)
            ->where('status', 'active')
            ->count();
    }

    public function countResponsesByIdentity(string $subjectType, string $subjectId, ?string $responseType = null): int
    {
        $query = Response::query()->forOwner()
            ->where('respondable_type', $subjectType)
            ->where('respondable_id', $subjectId)
            ->where('status', 'active');

        if ($responseType !== null) {
            $query->where('response_type', $responseType);
        }

        return $query->count();
    }

    public function countReactionsByIdentity(string $subjectType, string $subjectId, ?string $reactionType = null): int
    {
        $query = Reaction::query()->forOwner()
            ->where('reactable_type', $subjectType)
            ->where('reactable_id', $subjectId)
            ->where('status', 'active');

        if ($reactionType !== null) {
            $query->where('reaction_type', $reactionType);
        }

        return $query->count();
    }

    /** @return array<string, int> */
    public function groupedResponseCounts(string $subjectType, string $subjectId): array
    {
        /** @var array<string, int> $counts */
        $counts = Response::query()->forOwner()
            ->where('respondable_type', $subjectType)
            ->where('respondable_id', $subjectId)
            ->where('status', 'active')
            ->select('response_type')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('response_type')
            ->pluck('aggregate', 'response_type')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        return $counts;
    }

    /** @return array<string, int> */
    public function groupedReactionCounts(string $subjectType, string $subjectId): array
    {
        /** @var array<string, int> $counts */
        $counts = Reaction::query()->forOwner()
            ->where('reactable_type', $subjectType)
            ->where('reactable_id', $subjectId)
            ->where('status', 'active')
            ->select('reaction_type')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('reaction_type')
            ->pluck('aggregate', 'reaction_type')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        return $counts;
    }

    /** @return array<string, int> */
    public function aggregateCounters(string $subjectType, string $subjectId): array
    {
        $followers = Follow::query()->forOwner()
            ->where('followable_type', $subjectType)
            ->where('followable_id', $subjectId)
            ->where('status', 'active')
            ->selectRaw('COUNT(*) as followers');
        $bookmarks = Bookmark::query()->forOwner()
            ->where('bookmarkable_type', $subjectType)
            ->where('bookmarkable_id', $subjectId)
            ->where('status', 'active')
            ->selectRaw('COUNT(*) as bookmarks');
        $totals = (new Follow)->getConnection()->query()
            ->selectSub($followers, 'followers')
            ->selectSub($bookmarks, 'bookmarks')
            ->first();

        return [
            'followers' => (int) ($totals->followers ?? 0),
            'bookmarks' => (int) ($totals->bookmarks ?? 0),
        ];
    }
}
