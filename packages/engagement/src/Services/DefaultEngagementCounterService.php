<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Services;

use AIArmada\Engagement\Contracts\EngagementCounterService;
use AIArmada\Engagement\Models\Bookmark;
use AIArmada\Engagement\Models\EngagementCounter;
use AIArmada\Engagement\Models\Follow;
use AIArmada\Engagement\Models\Reaction;
use AIArmada\Engagement\Models\Response;
use Carbon\CarbonImmutable;

final class DefaultEngagementCounterService implements EngagementCounterService
{
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
                    'counter_key' => null,
                ],
                [
                    'count_value' => $count,
                    'recalculated_at' => CarbonImmutable::now(),
                ],
            );
        }
    }
}
