<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Queries;

use AIArmada\Engagement\Enums\ShareStatus;
use AIArmada\Engagement\Features\Interactions\Api\ReadsInteractionState;
use AIArmada\Engagement\Features\Interactions\Models\Bookmark;
use AIArmada\Engagement\Features\Interactions\Models\Follow;
use AIArmada\Engagement\Features\Interactions\Models\Reaction;
use AIArmada\Engagement\Features\Interactions\Models\Response;
use AIArmada\Engagement\Features\Interactions\Models\Share;
use AIArmada\Engagement\Support\EngagementModelGuard;

final readonly class ReadInteractionStateQuery implements ReadsInteractionState
{
    public function isFollowing(mixed $actor, mixed $subject): bool
    {
        $actor = EngagementModelGuard::identity(EngagementModelGuard::requireModel($actor, 'actor'), 'actor');
        $subject = EngagementModelGuard::identity(EngagementModelGuard::requireModel($subject, 'subject'), 'subject');

        return Follow::query()
            ->where('follower_type', $actor['type'])
            ->where('follower_id', $actor['id'])
            ->where('followable_type', $subject['type'])
            ->where('followable_id', $subject['id'])
            ->where('status', 'active')
            ->exists();
    }

    public function isBookmarked(mixed $actor, mixed $subject): bool
    {
        $actor = EngagementModelGuard::identity(EngagementModelGuard::requireModel($actor, 'actor'), 'actor');
        $subject = EngagementModelGuard::identity(EngagementModelGuard::requireModel($subject, 'subject'), 'subject');

        return Bookmark::query()
            ->where('bookmarker_type', $actor['type'])
            ->where('bookmarker_id', $actor['id'])
            ->where('bookmarkable_type', $subject['type'])
            ->where('bookmarkable_id', $subject['id'])
            ->where('status', 'active')
            ->exists();
    }

    public function responseFor(mixed $actor, mixed $subject): ?array
    {
        $actor = EngagementModelGuard::identity(EngagementModelGuard::requireModel($actor, 'actor'), 'actor');
        $subject = EngagementModelGuard::identity(EngagementModelGuard::requireModel($subject, 'subject'), 'subject');

        return Response::query()
            ->where('responder_type', $actor['type'])
            ->where('responder_id', $actor['id'])
            ->where('respondable_type', $subject['type'])
            ->where('respondable_id', $subject['id'])
            ->where('status', 'active')
            ->first()?->toArray();
    }

    public function reactionFor(mixed $actor, mixed $subject, ?string $reactionType = null): ?array
    {
        $actor = EngagementModelGuard::identity(EngagementModelGuard::requireModel($actor, 'actor'), 'actor');
        $subject = EngagementModelGuard::identity(EngagementModelGuard::requireModel($subject, 'subject'), 'subject');

        $query = Reaction::query()
            ->where('reactor_type', $actor['type'])
            ->where('reactor_id', $actor['id'])
            ->where('reactable_type', $subject['type'])
            ->where('reactable_id', $subject['id'])
            ->where('status', 'active');

        if ($reactionType !== null) {
            $query->where('reaction_type', $reactionType);
        }

        return $query->first()?->toArray();
    }

    public function shareFor(mixed $actor, mixed $subject): ?array
    {
        $actor = EngagementModelGuard::identity(EngagementModelGuard::requireModel($actor, 'actor'), 'actor');
        $subject = EngagementModelGuard::identity(EngagementModelGuard::requireModel($subject, 'subject'), 'subject');

        $share = Share::query()
            ->where('sharer_type', $actor['type'])
            ->where('sharer_id', $actor['id'])
            ->where('shareable_type', $subject['type'])
            ->where('shareable_id', $subject['id'])
            ->whereIn('status', [ShareStatus::Created, ShareStatus::Shared])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($share === null) {
            return null;
        }

        return [
            'id' => $share->getKey(),
            'share_url' => $share->share_url,
            'share_token' => $share->share_token,
            'channel' => $share->channel,
            'status' => $share->status,
            'shared_at' => $share->shared_at,
        ];
    }
}
