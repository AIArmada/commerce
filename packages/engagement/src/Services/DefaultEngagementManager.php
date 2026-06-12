<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Services;

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Contracts\ShareUrlGenerator;
use AIArmada\Engagement\Events\BookmarkAddedToCollection;
use AIArmada\Engagement\Events\BookmarkArchived;
use AIArmada\Engagement\Events\BookmarkCreated;
use AIArmada\Engagement\Events\BookmarkRemoved;
use AIArmada\Engagement\Events\BookmarkRemovedFromCollection;
use AIArmada\Engagement\Events\FollowCreated;
use AIArmada\Engagement\Events\FollowMuted;
use AIArmada\Engagement\Events\FollowRemoved;
use AIArmada\Engagement\Events\FollowUnmuted;
use AIArmada\Engagement\Events\ReactionCreated;
use AIArmada\Engagement\Events\ReactionRemoved;
use AIArmada\Engagement\Events\ResponseCancelled;
use AIArmada\Engagement\Events\ResponseChanged;
use AIArmada\Engagement\Events\ResponseCreated;
use AIArmada\Engagement\Events\ShareCompleted;
use AIArmada\Engagement\Events\ShareCreated;
use AIArmada\Engagement\Models\Bookmark;
use AIArmada\Engagement\Models\BookmarkCollectionItem;
use AIArmada\Engagement\Models\Follow;
use AIArmada\Engagement\Models\Reaction;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Models\Response;
use AIArmada\Engagement\Models\Share;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class DefaultEngagementManager implements EngagementManager
{
    public function __construct(
        private readonly EngagementPolicyResolver $policy,
        private readonly ReminderManager $reminderManager,
        private readonly ShareUrlGenerator $shareUrlGenerator,
    ) {}

    public function follow(mixed $actor, mixed $subject, array $options = []): Follow
    {
        $this->policy->canFollow($actor, $subject);

        $existing = Follow::query()
            ->where('follower_type', $actor->getMorphClass())
            ->where('follower_id', $actor->getKey())
            ->where('followable_type', $subject->getMorphClass())
            ->where('followable_id', $subject->getKey())
            ->first();

        if ($existing && $existing->status === 'active') {
            return $existing;
        }

        if ($existing && $existing->status !== 'active') {
            $existing->update([
                'status' => 'active',
                'unfollowed_at' => null,
                'followed_at' => CarbonImmutable::now(),
            ]);
            event(new FollowCreated($existing));

            return $existing;
        }

        $follow = Follow::query()->create([
            'follower_type' => $actor->getMorphClass(),
            'follower_id' => $actor->getKey(),
            'followable_type' => $subject->getMorphClass(),
            'followable_id' => $subject->getKey(),
            'status' => 'active',
            'notification_level' => $options['notification_level'] ?? null,
            'followed_at' => CarbonImmutable::now(),
            'source' => $options['source'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);

        event(new FollowCreated($follow));

        return $follow;
    }

    public function unfollow(mixed $actor, mixed $subject, array $options = []): void
    {
        $follow = Follow::query()
            ->where('follower_type', $actor->getMorphClass())
            ->where('follower_id', $actor->getKey())
            ->where('followable_type', $subject->getMorphClass())
            ->where('followable_id', $subject->getKey())
            ->where('status', 'active')
            ->first();

        if ($follow) {
            $follow->update(['status' => 'unfollowed', 'unfollowed_at' => CarbonImmutable::now()]);
            event(new FollowRemoved($follow));
        }
    }

    public function muteFollow(mixed $actor, mixed $subject, array $options = []): Follow
    {
        $follow = Follow::query()
            ->where('follower_type', $actor->getMorphClass())
            ->where('follower_id', $actor->getKey())
            ->where('followable_type', $subject->getMorphClass())
            ->where('followable_id', $subject->getKey())
            ->where('status', 'active')
            ->firstOrFail();

        $follow->update(['status' => 'muted', 'muted_at' => CarbonImmutable::now()]);
        event(new FollowMuted($follow));

        return $follow;
    }

    public function unmuteFollow(mixed $actor, mixed $subject, array $options = []): Follow
    {
        $follow = Follow::query()
            ->where('follower_type', $actor->getMorphClass())
            ->where('follower_id', $actor->getKey())
            ->where('followable_type', $subject->getMorphClass())
            ->where('followable_id', $subject->getKey())
            ->where('status', 'muted')
            ->firstOrFail();

        $follow->update(['status' => 'active', 'muted_at' => null]);
        event(new FollowUnmuted($follow));

        return $follow;
    }

    public function bookmark(mixed $actor, mixed $subject, array $options = []): Bookmark
    {
        $existing = Bookmark::query()
            ->where('bookmarker_type', $actor->getMorphClass())
            ->where('bookmarker_id', $actor->getKey())
            ->where('bookmarkable_type', $subject->getMorphClass())
            ->where('bookmarkable_id', $subject->getKey())
            ->first();

        if ($existing && $existing->status === 'active') {
            return $existing;
        }

        if ($existing) {
            $existing->update([
                'status' => 'active',
                'removed_at' => null,
                'bookmarked_at' => CarbonImmutable::now(),
            ]);
            event(new BookmarkCreated($existing));

            return $existing;
        }

        $bookmark = Bookmark::query()->create([
            'bookmarker_type' => $actor->getMorphClass(),
            'bookmarker_id' => $actor->getKey(),
            'bookmarkable_type' => $subject->getMorphClass(),
            'bookmarkable_id' => $subject->getKey(),
            'status' => 'active',
            'notes' => $options['notes'] ?? null,
            'bookmarked_at' => CarbonImmutable::now(),
            'source' => $options['source'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);

        event(new BookmarkCreated($bookmark));

        return $bookmark;
    }

    public function removeBookmark(mixed $actor, mixed $subject, array $options = []): void
    {
        $bookmark = Bookmark::query()
            ->where('bookmarker_type', $actor->getMorphClass())
            ->where('bookmarker_id', $actor->getKey())
            ->where('bookmarkable_type', $subject->getMorphClass())
            ->where('bookmarkable_id', $subject->getKey())
            ->where('status', 'active')
            ->first();

        if ($bookmark) {
            $bookmark->update(['status' => 'removed', 'removed_at' => CarbonImmutable::now()]);
            event(new BookmarkRemoved($bookmark));
        }
    }

    public function archiveBookmark(mixed $actor, mixed $subject, array $options = []): void
    {
        $bookmark = Bookmark::query()
            ->where('bookmarker_type', $actor->getMorphClass())
            ->where('bookmarker_id', $actor->getKey())
            ->where('bookmarkable_type', $subject->getMorphClass())
            ->where('bookmarkable_id', $subject->getKey())
            ->where('status', 'active')
            ->first();

        if ($bookmark) {
            $bookmark->update(['status' => 'archived', 'archived_at' => CarbonImmutable::now()]);
            event(new BookmarkArchived($bookmark));
        }
    }

    public function respond(mixed $actor, mixed $subject, string $responseType, array $options = []): Response
    {
        $this->policy->canRespond($actor, $subject, $responseType);

        $existing = Response::query()
            ->where('responder_type', $actor->getMorphClass())
            ->where('responder_id', $actor->getKey())
            ->where('respondable_type', $subject->getMorphClass())
            ->where('respondable_id', $subject->getKey())
            ->where('status', 'active')
            ->first();

        if ($existing) {
            $oldType = $existing->response_type;
            $existing->update([
                'response_type' => $responseType,
                'changed_at' => CarbonImmutable::now(),
                'metadata' => array_merge(
                    (array) $existing->metadata,
                    ['previous_response_type' => $oldType],
                ),
            ]);
            event(new ResponseChanged($existing, $oldType));

            return $existing;
        }

        $response = Response::query()->create([
            'responder_type' => $actor->getMorphClass(),
            'responder_id' => $actor->getKey(),
            'respondable_type' => $subject->getMorphClass(),
            'respondable_id' => $subject->getKey(),
            'response_type' => $responseType,
            'status' => 'active',
            'visibility' => $options['visibility'] ?? 'public',
            'responded_at' => CarbonImmutable::now(),
            'source' => $options['source'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);

        event(new ResponseCreated($response));

        return $response;
    }

    public function cancelResponse(mixed $actor, mixed $subject, array $options = []): void
    {
        $response = Response::query()
            ->where('responder_type', $actor->getMorphClass())
            ->where('responder_id', $actor->getKey())
            ->where('respondable_type', $subject->getMorphClass())
            ->where('respondable_id', $subject->getKey())
            ->where('status', 'active')
            ->first();

        if ($response) {
            $response->update(['status' => 'cancelled', 'cancelled_at' => CarbonImmutable::now()]);
            event(new ResponseCancelled($response));
        }
    }

    public function react(mixed $actor, mixed $subject, string $reactionType, array $options = []): Reaction
    {
        $this->policy->canReact($actor, $subject, $reactionType);

        $existing = Reaction::query()
            ->where('reactor_type', $actor->getMorphClass())
            ->where('reactor_id', $actor->getKey())
            ->where('reactable_type', $subject->getMorphClass())
            ->where('reactable_id', $subject->getKey())
            ->where('reaction_type', $reactionType)
            ->first();

        if ($existing && $existing->status === 'active') {
            return $existing;
        }

        if ($existing) {
            $existing->update([
                'status' => 'active',
                'removed_at' => null,
                'reacted_at' => CarbonImmutable::now(),
            ]);
            event(new ReactionCreated($existing));

            return $existing;
        }

        $reaction = Reaction::query()->create([
            'reactor_type' => $actor->getMorphClass(),
            'reactor_id' => $actor->getKey(),
            'reactable_type' => $subject->getMorphClass(),
            'reactable_id' => $subject->getKey(),
            'reaction_type' => $reactionType,
            'status' => 'active',
            'reacted_at' => CarbonImmutable::now(),
            'source' => $options['source'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);

        event(new ReactionCreated($reaction));

        return $reaction;
    }

    public function removeReaction(mixed $actor, mixed $subject, ?string $reactionType = null, array $options = []): void
    {
        $query = Reaction::query()
            ->where('reactor_type', $actor->getMorphClass())
            ->where('reactor_id', $actor->getKey())
            ->where('reactable_type', $subject->getMorphClass())
            ->where('reactable_id', $subject->getKey())
            ->where('status', 'active');

        if ($reactionType) {
            $query->where('reaction_type', $reactionType);
        }

        foreach ($query->get() as $reaction) {
            $reaction->update(['status' => 'removed', 'removed_at' => CarbonImmutable::now()]);
            event(new ReactionRemoved($reaction));
        }
    }

    public function remind(mixed $actor, mixed $subject, array $options = []): Reminder
    {
        return $this->reminderManager->setReminder($actor, $subject, $options['reminder_type'] ?? 'before_start', $options);
    }

    public function share(mixed $actor, mixed $subject, array $options = []): Share
    {
        $share = Share::query()->create([
            'sharer_type' => $actor->getMorphClass(),
            'sharer_id' => $actor->getKey(),
            'shareable_type' => $subject->getMorphClass(),
            'shareable_id' => $subject->getKey(),
            'channel' => $options['channel'] ?? null,
            'destination' => $options['destination'] ?? null,
            'share_token' => $options['token'] ?? Str::random(16),
            'message' => $options['message'] ?? null,
            'status' => Share::STATUS_CREATED,
            'share_intent_at' => CarbonImmutable::now(),
            'metadata' => $options['metadata'] ?? null,
        ]);

        event(new ShareCreated($share));

        if ($options['complete'] ?? true) {
            $shareUrl = $this->shareUrlGenerator->generateShareUrl($subject, $options);
            $share->update([
                'share_url' => $shareUrl,
                'status' => Share::STATUS_SHARED,
                'shared_at' => CarbonImmutable::now(),
            ]);
            event(new ShareCompleted($share));
        }

        return $share;
    }

    public function addBookmarkToCollection(mixed $actor, mixed $bookmark, mixed $collection, array $options = []): void
    {
        BookmarkCollectionItem::query()->firstOrCreate([
            'bookmark_collection_id' => $collection->getKey(),
            'bookmark_id' => $bookmark->getKey(),
        ], [
            'added_at' => CarbonImmutable::now(),
            'notes' => $options['notes'] ?? null,
        ]);

        event(new BookmarkAddedToCollection($bookmark, $collection));
    }

    public function removeBookmarkFromCollection(mixed $actor, mixed $bookmark, mixed $collection, array $options = []): void
    {
        $item = BookmarkCollectionItem::query()
            ->where('bookmark_collection_id', $collection->getKey())
            ->where('bookmark_id', $bookmark->getKey())
            ->first();

        if ($item) {
            $item->update(['removed_at' => CarbonImmutable::now()]);
            event(new BookmarkRemovedFromCollection($bookmark, $collection));
        }
    }
}
