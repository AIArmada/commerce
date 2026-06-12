<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Services;

use AIArmada\Engagement\Contracts\EngagementStateResolver;
use AIArmada\Engagement\Models\Bookmark;
use AIArmada\Engagement\Models\Follow;
use AIArmada\Engagement\Models\Reaction;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Models\Response;
use AIArmada\Engagement\Models\Subscription;

final class DefaultEngagementStateResolver implements EngagementStateResolver
{
    public function isFollowing(mixed $actor, mixed $subject): bool
    {
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
        return Bookmark::query()
            ->where('bookmarker_type', $actor->getMorphClass())
            ->where('bookmarker_id', $actor->getKey())
            ->where('bookmarkable_type', $subject->getMorphClass())
            ->where('bookmarkable_id', $subject->getKey())
            ->where('status', 'active')
            ->exists();
    }

    public function responseFor(mixed $actor, mixed $subject): ?Response
    {
        return Response::query()
            ->where('responder_type', $actor->getMorphClass())
            ->where('responder_id', $actor->getKey())
            ->where('respondable_type', $subject->getMorphClass())
            ->where('respondable_id', $subject->getKey())
            ->where('status', 'active')
            ->first();
    }

    public function reactionFor(mixed $actor, mixed $subject, ?string $reactionType = null): ?Reaction
    {
        $query = Reaction::query()
            ->where('reactor_type', $actor->getMorphClass())
            ->where('reactor_id', $actor->getKey())
            ->where('reactable_type', $subject->getMorphClass())
            ->where('reactable_id', $subject->getKey())
            ->where('status', 'active');

        if ($reactionType !== null) {
            $query->where('reaction_type', $reactionType);
        }

        return $query->first();
    }

    public function subscriptionsFor(mixed $subscriber, mixed $subject = null): iterable
    {
        $query = Subscription::query()
            ->where('subscriber_type', $subscriber->getMorphClass())
            ->where('subscriber_id', $subscriber->getKey())
            ->where('status', 'active');

        if ($subject !== null) {
            $query
                ->where('subscribable_type', $subject->getMorphClass())
                ->where('subscribable_id', $subject->getKey());
        }

        return $query->cursor();
    }

    public function remindersFor(mixed $recipient, mixed $subject): iterable
    {
        return Reminder::query()
            ->where('recipient_type', $recipient->getMorphClass())
            ->where('recipient_id', $recipient->getKey())
            ->where('remindable_type', $subject->getMorphClass())
            ->where('remindable_id', $subject->getKey())
            ->whereIn('status', ['pending', 'scheduled'])
            ->cursor();
    }
}
