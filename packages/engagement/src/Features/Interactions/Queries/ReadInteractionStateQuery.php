<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Queries;

use AIArmada\Engagement\Features\Interactions\Api\ReadsInteractionState;
use AIArmada\Engagement\Features\Interactions\Models\Bookmark;
use AIArmada\Engagement\Features\Interactions\Models\Follow;
use AIArmada\Engagement\Features\Interactions\Models\Reaction;
use AIArmada\Engagement\Features\Interactions\Models\Response;
use AIArmada\Engagement\Support\EngagementModelGuard;

final class ReadInteractionStateQuery implements ReadsInteractionState
{
    public function isFollowing(mixed $actor, mixed $subject): bool
    {
        $actor = EngagementModelGuard::requireModel($actor, 'actor');
        $subject = EngagementModelGuard::requireModel($subject, 'subject');

        return Follow::query()
            ->where('follower_type', $actor->getMorphClass())
            ->where('follower_id', $actor->getKey())
            ->where('followable_type', $subject->getMorphClass())
            ->where('followable_id', $subject->getKey())
            ->where('status', 'active')
            ->exists();
    }

    public function isBookmarked(mixed $actor, mixed $subject): bool
    {
        $actor = EngagementModelGuard::requireModel($actor, 'actor');
        $subject = EngagementModelGuard::requireModel($subject, 'subject');

        return Bookmark::query()
            ->where('bookmarker_type', $actor->getMorphClass())
            ->where('bookmarker_id', $actor->getKey())
            ->where('bookmarkable_type', $subject->getMorphClass())
            ->where('bookmarkable_id', $subject->getKey())
            ->where('status', 'active')
            ->exists();
    }

    public function responseFor(mixed $actor, mixed $subject): ?array
    {
        $actor = EngagementModelGuard::requireModel($actor, 'actor');
        $subject = EngagementModelGuard::requireModel($subject, 'subject');

        return Response::query()
            ->where('responder_type', $actor->getMorphClass())
            ->where('responder_id', $actor->getKey())
            ->where('respondable_type', $subject->getMorphClass())
            ->where('respondable_id', $subject->getKey())
            ->where('status', 'active')
            ->first()?->toArray();
    }

    public function reactionFor(mixed $actor, mixed $subject, ?string $reactionType = null): ?array
    {
        $actor = EngagementModelGuard::requireModel($actor, 'actor');
        $subject = EngagementModelGuard::requireModel($subject, 'subject');

        $query = Reaction::query()
            ->where('reactor_type', $actor->getMorphClass())
            ->where('reactor_id', $actor->getKey())
            ->where('reactable_type', $subject->getMorphClass())
            ->where('reactable_id', $subject->getKey())
            ->where('status', 'active');

        if ($reactionType !== null) {
            $query->where('reaction_type', $reactionType);
        }

        return $query->first()?->toArray();
    }
}
