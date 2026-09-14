<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Services;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Engagement\Contracts\Bookmarkable;
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\Followable;
use AIArmada\Engagement\Contracts\Reactable;
use AIArmada\Engagement\Contracts\Remindable;
use AIArmada\Engagement\Contracts\ReminderManager;
use AIArmada\Engagement\Contracts\Respondable;
use AIArmada\Engagement\Contracts\Shareable;
use AIArmada\Engagement\Contracts\ShareUrlGenerator;
use AIArmada\Engagement\Enums\BookmarkStatus;
use AIArmada\Engagement\Enums\FollowStatus;
use AIArmada\Engagement\Enums\ReactionStatus;
use AIArmada\Engagement\Enums\ResponseStatus;
use AIArmada\Engagement\Enums\ShareStatus;
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
use AIArmada\Engagement\Models\BookmarkCollection;
use AIArmada\Engagement\Models\BookmarkCollectionItem;
use AIArmada\Engagement\Models\Follow;
use AIArmada\Engagement\Models\Reaction;
use AIArmada\Engagement\Models\Reminder;
use AIArmada\Engagement\Models\Response;
use AIArmada\Engagement\Models\Share;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DefaultEngagementManager implements EngagementManager
{
    public function __construct(
        private readonly EngagementPolicyResolver $policy,
        private readonly ReminderManager $reminderManager,
        private readonly ShareUrlGenerator $shareUrlGenerator,
    ) {}

    public function follow(CanInteract $actor, Followable $subject, array $options = []): Follow
    {
        $this->assertModels($actor, $subject, Followable::class);

        $notificationLevel = EngagementModelGuard::boundedString(
            $options['notification_level'] ?? config('engagement.defaults.follow_notification_level', 'all'),
            'notification_level',
        );
        $source = EngagementModelGuard::boundedString($options['source'] ?? null, 'source');
        $metadata = EngagementModelGuard::optionalArray($options['metadata'] ?? null, 'metadata');

        $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
        $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');

        try {
            return DB::transaction(function () use ($actor, $subject, $notificationLevel, $source, $metadata, $actorIdentity, $subjectIdentity): Follow {
                $this->authorize(
                    $this->policy->canFollow($actor, $subject),
                    'Following this subject is not authorized.',
                );

                $existing = Follow::query()
                    ->where('follower_type', $actorIdentity['type'])
                    ->where('follower_id', $actorIdentity['id'])
                    ->where('followable_type', $subjectIdentity['type'])
                    ->where('followable_id', $subjectIdentity['id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->status === FollowStatus::Active) {
                    return $existing;
                }

                if ($existing && $existing->status !== FollowStatus::Active) {
                    $existing->update([
                        'status' => 'active',
                        'unfollowed_at' => null,
                        'followed_at' => CarbonImmutable::now(),
                    ]);
                    event(new FollowCreated($existing));

                    return $existing;
                }

                $follow = Follow::query()->create([
                    'follower_type' => $actorIdentity['type'],
                    'follower_id' => $actorIdentity['id'],
                    'followable_type' => $subjectIdentity['type'],
                    'followable_id' => $subjectIdentity['id'],
                    'status' => 'active',
                    'notification_level' => $notificationLevel,
                    'followed_at' => CarbonImmutable::now(),
                    'source' => $source,
                    'metadata' => $metadata,
                ]);

                event(new FollowCreated($follow));

                return $follow;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = Follow::query()
                ->where('follower_type', $actorIdentity['type'])
                ->where('follower_id', $actorIdentity['id'])
                ->where('followable_type', $subjectIdentity['type'])
                ->where('followable_id', $subjectIdentity['id'])
                ->first();

            if ($existing instanceof Follow) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function unfollow(CanInteract $actor, Followable $subject, array $options = []): void
    {
        $this->assertModels($actor, $subject, Followable::class);

        DB::transaction(function () use ($actor, $subject): void {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $follow = Follow::query()
                ->where('follower_type', $actorIdentity['type'])
                ->where('follower_id', $actorIdentity['id'])
                ->where('followable_type', $subjectIdentity['type'])
                ->where('followable_id', $subjectIdentity['id'])
                ->where('status', 'active')
                ->first();

            if ($follow) {
                $follow->update(['status' => 'unfollowed', 'unfollowed_at' => CarbonImmutable::now()]);
                event(new FollowRemoved($follow));
            }
        });
    }

    public function muteFollow(CanInteract $actor, Followable $subject, array $options = []): Follow
    {
        $this->assertModels($actor, $subject, Followable::class);

        return DB::transaction(function () use ($actor, $subject): Follow {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $follow = Follow::query()
                ->where('follower_type', $actorIdentity['type'])
                ->where('follower_id', $actorIdentity['id'])
                ->where('followable_type', $subjectIdentity['type'])
                ->where('followable_id', $subjectIdentity['id'])
                ->where('status', 'active')
                ->firstOrFail();

            $follow->update(['status' => 'muted', 'muted_at' => CarbonImmutable::now()]);
            event(new FollowMuted($follow));

            return $follow;
        });
    }

    public function unmuteFollow(CanInteract $actor, Followable $subject, array $options = []): Follow
    {
        $this->assertModels($actor, $subject, Followable::class);

        return DB::transaction(function () use ($actor, $subject): Follow {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $follow = Follow::query()
                ->where('follower_type', $actorIdentity['type'])
                ->where('follower_id', $actorIdentity['id'])
                ->where('followable_type', $subjectIdentity['type'])
                ->where('followable_id', $subjectIdentity['id'])
                ->where('status', 'muted')
                ->firstOrFail();

            $follow->update(['status' => 'active', 'muted_at' => null]);
            event(new FollowUnmuted($follow));

            return $follow;
        });
    }

    public function bookmark(CanInteract $actor, Bookmarkable $subject, array $options = []): Bookmark
    {
        $this->assertModels($actor, $subject, Bookmarkable::class);

        $notes = EngagementModelGuard::boundedString($options['notes'] ?? null, 'notes', EngagementModelGuard::TEXT_MAX_LENGTH);
        $source = EngagementModelGuard::boundedString($options['source'] ?? null, 'source');
        $metadata = EngagementModelGuard::optionalArray($options['metadata'] ?? null, 'metadata');

        $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
        $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');

        try {
            return DB::transaction(function () use ($actor, $subject, $notes, $source, $metadata, $actorIdentity, $subjectIdentity): Bookmark {
                $this->authorize(
                    $this->policy->canBookmark($actor, $subject),
                    'Bookmarking this subject is not authorized.',
                );

                $existing = Bookmark::query()
                    ->where('bookmarker_type', $actorIdentity['type'])
                    ->where('bookmarker_id', $actorIdentity['id'])
                    ->where('bookmarkable_type', $subjectIdentity['type'])
                    ->where('bookmarkable_id', $subjectIdentity['id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->status === BookmarkStatus::Active) {
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
                    'bookmarker_type' => $actorIdentity['type'],
                    'bookmarker_id' => $actorIdentity['id'],
                    'bookmarkable_type' => $subjectIdentity['type'],
                    'bookmarkable_id' => $subjectIdentity['id'],
                    'status' => 'active',
                    'notes' => $notes,
                    'bookmarked_at' => CarbonImmutable::now(),
                    'source' => $source,
                    'metadata' => $metadata,
                ]);

                event(new BookmarkCreated($bookmark));

                return $bookmark;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = Bookmark::query()
                ->where('bookmarker_type', $actorIdentity['type'])
                ->where('bookmarker_id', $actorIdentity['id'])
                ->where('bookmarkable_type', $subjectIdentity['type'])
                ->where('bookmarkable_id', $subjectIdentity['id'])
                ->first();

            if ($existing instanceof Bookmark) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function removeBookmark(CanInteract $actor, Bookmarkable $subject, array $options = []): void
    {
        $this->assertModels($actor, $subject, Bookmarkable::class);

        DB::transaction(function () use ($actor, $subject): void {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $bookmark = Bookmark::query()
                ->where('bookmarker_type', $actorIdentity['type'])
                ->where('bookmarker_id', $actorIdentity['id'])
                ->where('bookmarkable_type', $subjectIdentity['type'])
                ->where('bookmarkable_id', $subjectIdentity['id'])
                ->where('status', 'active')
                ->first();

            if ($bookmark) {
                $bookmark->update(['status' => 'removed', 'removed_at' => CarbonImmutable::now()]);
                event(new BookmarkRemoved($bookmark));
            }
        });
    }

    public function archiveBookmark(CanInteract $actor, Bookmarkable $subject, array $options = []): void
    {
        $this->assertModels($actor, $subject, Bookmarkable::class);

        DB::transaction(function () use ($actor, $subject): void {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $bookmark = Bookmark::query()
                ->where('bookmarker_type', $actorIdentity['type'])
                ->where('bookmarker_id', $actorIdentity['id'])
                ->where('bookmarkable_type', $subjectIdentity['type'])
                ->where('bookmarkable_id', $subjectIdentity['id'])
                ->where('status', 'active')
                ->first();

            if ($bookmark) {
                $bookmark->update(['status' => 'archived', 'archived_at' => CarbonImmutable::now()]);
                event(new BookmarkArchived($bookmark));
            }
        });
    }

    public function respond(CanInteract $actor, Respondable $subject, string $responseType, array $options = []): Response
    {
        $this->assertModels($actor, $subject, Respondable::class);

        $responseType = EngagementModelGuard::requiredString($responseType, 'response_type');
        $visibility = EngagementModelGuard::boundedString(
            $options['visibility'] ?? config('engagement.defaults.response_visibility', 'public'),
            'visibility',
        );
        $source = EngagementModelGuard::boundedString($options['source'] ?? null, 'source');
        $metadata = EngagementModelGuard::optionalArray($options['metadata'] ?? null, 'metadata');

        $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
        $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');

        try {
            return DB::transaction(function () use ($actor, $subject, $responseType, $visibility, $source, $metadata, $actorIdentity, $subjectIdentity): Response {
                $this->authorize(
                    $this->policy->canRespond($actor, $subject, $responseType),
                    'Responding to this subject is not authorized.',
                );

                $existing = Response::query()
                    ->where('responder_type', $actorIdentity['type'])
                    ->where('responder_id', $actorIdentity['id'])
                    ->where('respondable_type', $subjectIdentity['type'])
                    ->where('respondable_id', $subjectIdentity['id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof Response && $existing->status !== ResponseStatus::Active) {
                    $existing->update([
                        'response_type' => $responseType,
                        'status' => ResponseStatus::Active,
                        'responded_at' => CarbonImmutable::now(),
                        'changed_at' => null,
                        'cancelled_at' => null,
                    ]);
                    event(new ResponseCreated($existing));

                    return $existing;
                }

                if ($existing instanceof Response) {
                    if ($existing->response_type === $responseType) {
                        return $existing;
                    }

                    $oldType = $existing->response_type;
                    $existing->update([
                        'response_type' => $responseType,
                        'status' => ResponseStatus::Active,
                        'changed_at' => CarbonImmutable::now(),
                        'cancelled_at' => null,
                        'metadata' => array_merge(
                            (array) $existing->metadata,
                            ['previous_response_type' => $oldType],
                        ),
                    ]);
                    event(new ResponseChanged($existing, $oldType));

                    return $existing;
                }

                $response = Response::query()->create([
                    'responder_type' => $actorIdentity['type'],
                    'responder_id' => $actorIdentity['id'],
                    'respondable_type' => $subjectIdentity['type'],
                    'respondable_id' => $subjectIdentity['id'],
                    'response_type' => $responseType,
                    'status' => 'active',
                    'visibility' => $visibility,
                    'responded_at' => CarbonImmutable::now(),
                    'source' => $source,
                    'metadata' => $metadata,
                ]);

                event(new ResponseCreated($response));

                return $response;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = Response::query()
                ->where('responder_type', $actorIdentity['type'])
                ->where('responder_id', $actorIdentity['id'])
                ->where('respondable_type', $subjectIdentity['type'])
                ->where('respondable_id', $subjectIdentity['id'])
                ->first();

            if ($existing instanceof Response) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function cancelResponse(CanInteract $actor, Respondable $subject, array $options = []): void
    {
        $this->assertModels($actor, $subject, Respondable::class);

        DB::transaction(function () use ($actor, $subject): void {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $response = Response::query()
                ->where('responder_type', $actorIdentity['type'])
                ->where('responder_id', $actorIdentity['id'])
                ->where('respondable_type', $subjectIdentity['type'])
                ->where('respondable_id', $subjectIdentity['id'])
                ->where('status', 'active')
                ->first();

            if ($response) {
                $response->update(['status' => 'cancelled', 'cancelled_at' => CarbonImmutable::now()]);
                event(new ResponseCancelled($response));
            }
        });
    }

    public function react(CanInteract $actor, Reactable $subject, string $reactionType, array $options = []): Reaction
    {
        $this->assertModels($actor, $subject, Reactable::class);

        $reactionType = EngagementModelGuard::requiredString($reactionType, 'reaction_type');
        $source = EngagementModelGuard::boundedString($options['source'] ?? null, 'source');
        $metadata = EngagementModelGuard::optionalArray($options['metadata'] ?? null, 'metadata');

        $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
        $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');

        try {
            return DB::transaction(function () use ($actor, $subject, $reactionType, $source, $metadata, $actorIdentity, $subjectIdentity): Reaction {
                $this->authorize(
                    $this->policy->canReact($actor, $subject, $reactionType),
                    'Reacting to this subject is not authorized.',
                );

                $existing = Reaction::query()
                    ->where('reactor_type', $actorIdentity['type'])
                    ->where('reactor_id', $actorIdentity['id'])
                    ->where('reactable_type', $subjectIdentity['type'])
                    ->where('reactable_id', $subjectIdentity['id'])
                    ->where('reaction_type', $reactionType)
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->status === ReactionStatus::Active) {
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
                    'reactor_type' => $actorIdentity['type'],
                    'reactor_id' => $actorIdentity['id'],
                    'reactable_type' => $subjectIdentity['type'],
                    'reactable_id' => $subjectIdentity['id'],
                    'reaction_type' => $reactionType,
                    'status' => 'active',
                    'reacted_at' => CarbonImmutable::now(),
                    'source' => $source,
                    'metadata' => $metadata,
                ]);

                event(new ReactionCreated($reaction));

                return $reaction;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = Reaction::query()
                ->where('reactor_type', $actorIdentity['type'])
                ->where('reactor_id', $actorIdentity['id'])
                ->where('reactable_type', $subjectIdentity['type'])
                ->where('reactable_id', $subjectIdentity['id'])
                ->where('reaction_type', $reactionType)
                ->first();

            if ($existing instanceof Reaction) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function removeReaction(CanInteract $actor, Reactable $subject, ?string $reactionType = null, array $options = []): void
    {
        $this->assertModels($actor, $subject, Reactable::class);

        DB::transaction(function () use ($actor, $subject, $reactionType): void {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $query = Reaction::query()
                ->where('reactor_type', $actorIdentity['type'])
                ->where('reactor_id', $actorIdentity['id'])
                ->where('reactable_type', $subjectIdentity['type'])
                ->where('reactable_id', $subjectIdentity['id'])
                ->where('status', 'active');

            if ($reactionType) {
                $query->where('reaction_type', $reactionType);
            }

            foreach ($query->get() as $reaction) {
                $reaction->update(['status' => 'removed', 'removed_at' => CarbonImmutable::now()]);
                event(new ReactionRemoved($reaction));
            }
        });
    }

    public function remind(CanInteract $actor, Remindable $subject, array $options = []): Reminder
    {
        $this->assertModels($actor, $subject, Remindable::class);

        return $this->reminderManager->setReminder($actor, $subject, $options['reminder_type'] ?? 'before_start', $options);
    }

    public function share(CanInteract $actor, Shareable $subject, array $options = []): Share
    {
        $this->assertModels($actor, $subject, Shareable::class);

        $channel = EngagementModelGuard::boundedString($options['channel'] ?? null, 'channel');
        $destination = EngagementModelGuard::boundedString($options['destination'] ?? null, 'destination');
        $message = EngagementModelGuard::boundedString($options['message'] ?? null, 'message', EngagementModelGuard::TEXT_MAX_LENGTH);
        $metadata = EngagementModelGuard::optionalArray($options['metadata'] ?? null, 'metadata');
        $explicitToken = EngagementModelGuard::boundedString($options['token'] ?? null, 'token');
        $token = ($explicitToken === null || $explicitToken === '') ? Str::random(32) : $explicitToken;

        return DB::transaction(function () use ($actor, $subject, $options, $channel, $destination, $message, $metadata, $token): Share {
            $this->authorize(
                $this->policy->canShare($actor, $subject),
                'Sharing this subject is not authorized.',
            );

            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $share = Share::query()->create([
                'sharer_type' => $actorIdentity['type'],
                'sharer_id' => $actorIdentity['id'],
                'shareable_type' => $subjectIdentity['type'],
                'shareable_id' => $subjectIdentity['id'],
                'channel' => $channel,
                'destination' => $destination,
                'share_token' => $token,
                'message' => $message,
                'status' => ShareStatus::Created,
                'share_intent_at' => CarbonImmutable::now(),
                'metadata' => $metadata,
            ]);

            event(new ShareCreated($share));

            if ($options['complete'] ?? true) {
                $shareUrl = $this->shareUrlGenerator->generateShareUrl($subject, array_merge($options, ['token' => $token]));
                $share->update([
                    'share_url' => $shareUrl,
                    'status' => ShareStatus::Shared,
                    'shared_at' => CarbonImmutable::now(),
                ]);
                event(new ShareCompleted($share));
            }

            return $share;
        });
    }

    public function addBookmarkToCollection(CanInteract $actor, Bookmark $bookmark, BookmarkCollection $collection, array $options = []): void
    {
        EngagementModelGuard::assertContract($actor, CanInteract::class, 'actor');

        $notes = EngagementModelGuard::boundedString($options['notes'] ?? null, 'notes', EngagementModelGuard::TEXT_MAX_LENGTH);
        $bookmarkId = $bookmark->getKey();
        $collectionId = $collection->getKey();

        try {
            DB::transaction(function () use ($bookmarkId, $collectionId, $notes): void {
                $bookmark = OwnerWriteGuard::findOrFailForOwner(Bookmark::class, $bookmarkId);
                $collection = OwnerWriteGuard::findOrFailForOwner(BookmarkCollection::class, $collectionId);

                $item = BookmarkCollectionItem::query()
                    ->where('bookmark_collection_id', $collection->getKey())
                    ->where('bookmark_id', $bookmark->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($item instanceof BookmarkCollectionItem) {
                    if ($item->removed_at !== null) {
                        $item->update([
                            'removed_at' => null,
                            'added_at' => CarbonImmutable::now(),
                            'notes' => $notes ?? $item->notes,
                        ]);
                    }

                    event(new BookmarkAddedToCollection($bookmark, $collection));

                    return;
                }

                BookmarkCollectionItem::query()->create([
                    'bookmark_collection_id' => $collection->getKey(),
                    'bookmark_id' => $bookmark->getKey(),
                    'added_at' => CarbonImmutable::now(),
                    'notes' => $notes,
                ]);

                event(new BookmarkAddedToCollection($bookmark, $collection));
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $item = BookmarkCollectionItem::query()
                ->where('bookmark_collection_id', $collectionId)
                ->where('bookmark_id', $bookmarkId)
                ->first();

            if (! $item instanceof BookmarkCollectionItem) {
                throw $exception;
            }
        }
    }

    public function removeBookmarkFromCollection(CanInteract $actor, Bookmark $bookmark, BookmarkCollection $collection, array $options = []): void
    {
        EngagementModelGuard::assertContract($actor, CanInteract::class, 'actor');

        DB::transaction(function () use ($bookmark, $collection): void {
            $bookmark = OwnerWriteGuard::findOrFailForOwner(Bookmark::class, $bookmark->getKey());
            $collection = OwnerWriteGuard::findOrFailForOwner(BookmarkCollection::class, $collection->getKey());
            $item = BookmarkCollectionItem::query()
                ->where('bookmark_collection_id', $collection->getKey())
                ->where('bookmark_id', $bookmark->getKey())
                ->first();

            if ($item) {
                $item->update(['removed_at' => CarbonImmutable::now()]);
                event(new BookmarkRemovedFromCollection($bookmark, $collection));
            }
        });
    }

    private function assertModels(CanInteract $actor, object $subject, string $subjectContract): void
    {
        EngagementModelGuard::assertContract($actor, CanInteract::class, 'actor');
        EngagementModelGuard::assertContract($subject, $subjectContract, 'subject');
    }

    private function authorize(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw new AuthorizationException($message);
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
