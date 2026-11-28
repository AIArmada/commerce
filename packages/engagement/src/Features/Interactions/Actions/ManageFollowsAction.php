<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Actions;

use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\Followable;
use AIArmada\Engagement\Enums\FollowStatus;
use AIArmada\Engagement\Events\FollowCreated;
use AIArmada\Engagement\Events\FollowMuted;
use AIArmada\Engagement\Events\FollowRemoved;
use AIArmada\Engagement\Events\FollowUnmuted;
use AIArmada\Engagement\Features\Interactions\Models\Follow;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

final readonly class ManageFollowsAction
{
    public function __construct(
        private EngagementPolicyResolver $policy,
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
            return (new Follow)->getConnection()->transaction(function () use ($actor, $subject, $notificationLevel, $source, $metadata, $actorIdentity, $subjectIdentity): Follow {
                $this->authorize(
                    $this->policy->canFollow($actor, $subject),
                    'Following this subject is not authorized.',
                );

                $existing = Follow::query()->forOwner()
                    ->where('follower_type', $actorIdentity['type'])
                    ->where('follower_id', $actorIdentity['id'])
                    ->where('followable_type', $subjectIdentity['type'])
                    ->where('followable_id', $subjectIdentity['id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->status === FollowStatus::Active) {
                    return $existing;
                }

                if ($existing && $existing->status === FollowStatus::Blocked) {
                    throw new AuthorizationException('This follow is blocked.');
                }

                if ($existing && $existing->status !== FollowStatus::Active) {
                    $existing->update([
                        'status' => 'active',
                        'unfollowed_at' => null,
                        'muted_at' => null,
                        'followed_at' => CarbonImmutable::now(),
                    ]);
                    event(new FollowCreated(
                        $existing->id,
                        $existing->follower_type,
                        $existing->follower_id,
                        $existing->followable_type,
                        $existing->followable_id,
                        $existing->owner_type,
                        $existing->owner_id,
                    ));

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

                event(new FollowCreated(
                    $follow->id,
                    $follow->follower_type,
                    $follow->follower_id,
                    $follow->followable_type,
                    $follow->followable_id,
                    $follow->owner_type,
                    $follow->owner_id,
                ));

                return $follow;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = Follow::query()->forOwner()
                ->where('follower_type', $actorIdentity['type'])
                ->where('follower_id', $actorIdentity['id'])
                ->where('followable_type', $subjectIdentity['type'])
                ->where('followable_id', $subjectIdentity['id'])
                ->first();

            if ($existing instanceof Follow && $existing->status === FollowStatus::Blocked) {
                throw new AuthorizationException('This follow is blocked.');
            }

            if ($existing instanceof Follow && $existing->status === FollowStatus::Active) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function unfollow(CanInteract $actor, Followable $subject, array $options = []): void
    {
        $this->assertModels($actor, $subject, Followable::class);

        (new Follow)->getConnection()->transaction(function () use ($actor, $subject): void {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $follow = Follow::query()->forOwner()
                ->where('follower_type', $actorIdentity['type'])
                ->where('follower_id', $actorIdentity['id'])
                ->where('followable_type', $subjectIdentity['type'])
                ->where('followable_id', $subjectIdentity['id'])
                ->whereIn('status', [FollowStatus::Active, FollowStatus::Muted])
                ->lockForUpdate()
                ->first();

            if ($follow) {
                $wasActive = $follow->status === FollowStatus::Active;
                $follow->update([
                    'status' => 'unfollowed',
                    'unfollowed_at' => CarbonImmutable::now(),
                    'muted_at' => null,
                ]);
                if ($wasActive) {
                    event(new FollowRemoved(
                        $follow->id,
                        $follow->follower_type,
                        $follow->follower_id,
                        $follow->followable_type,
                        $follow->followable_id,
                        $follow->owner_type,
                        $follow->owner_id,
                    ));
                }
            }
        });
    }

    public function muteFollow(CanInteract $actor, Followable $subject, array $options = []): Follow
    {
        $this->assertModels($actor, $subject, Followable::class);

        return (new Follow)->getConnection()->transaction(function () use ($actor, $subject): Follow {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $follow = Follow::query()->forOwner()
                ->where('follower_type', $actorIdentity['type'])
                ->where('follower_id', $actorIdentity['id'])
                ->where('followable_type', $subjectIdentity['type'])
                ->where('followable_id', $subjectIdentity['id'])
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $follow->update(['status' => 'muted', 'muted_at' => CarbonImmutable::now()]);
            event(new FollowMuted(
                $follow->id,
                $follow->follower_type,
                $follow->follower_id,
                $follow->followable_type,
                $follow->followable_id,
                $follow->owner_type,
                $follow->owner_id,
            ));

            return $follow;
        });
    }

    public function unmuteFollow(CanInteract $actor, Followable $subject, array $options = []): Follow
    {
        $this->assertModels($actor, $subject, Followable::class);

        return (new Follow)->getConnection()->transaction(function () use ($actor, $subject): Follow {
            $actorIdentity = EngagementModelGuard::identity($actor, 'actor');
            $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
            $follow = Follow::query()->forOwner()
                ->where('follower_type', $actorIdentity['type'])
                ->where('follower_id', $actorIdentity['id'])
                ->where('followable_type', $subjectIdentity['type'])
                ->where('followable_id', $subjectIdentity['id'])
                ->where('status', 'muted')
                ->lockForUpdate()
                ->firstOrFail();

            $follow->update(['status' => 'active', 'muted_at' => null]);
            event(new FollowUnmuted(
                $follow->id,
                $follow->follower_type,
                $follow->follower_id,
                $follow->followable_type,
                $follow->followable_id,
                $follow->owner_type,
                $follow->owner_id,
            ));

            return $follow;
        });
    }

    private function assertModels(CanInteract $actor, object $subject, string $subjectContract): void
    {
        EngagementModelGuard::assertContract($actor, CanInteract::class, 'actor');
        EngagementModelGuard::assertContract($subject, $subjectContract, 'subject');
        EngagementModelGuard::assertWritableIdentity($actor, 'actor');
        EngagementModelGuard::assertWritableIdentity($subject, 'subject');
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
