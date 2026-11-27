<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Subscriptions\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Engagement\Contracts\CanInteract;
use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\Subscribable;
use AIArmada\Engagement\Enums\SubscriptionStatus;
use AIArmada\Engagement\Events\SubscriptionCancelled;
use AIArmada\Engagement\Events\SubscriptionCreated;
use AIArmada\Engagement\Events\SubscriptionMatched;
use AIArmada\Engagement\Events\SubscriptionMuted;
use AIArmada\Engagement\Events\SubscriptionUnmuted;
use AIArmada\Engagement\Features\Subscriptions\Models\Subscription;
use AIArmada\Engagement\Features\Subscriptions\Support\SubscriptionCriteria;
use AIArmada\Engagement\Support\EngagementModelGuard;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

final readonly class ManageSubscriptionsAction
{
    public function __construct(
        private readonly EngagementPolicyResolver $policy,
    ) {}

    public function subscribe(CanInteract $subscriber, ?Subscribable $subject = null, string $subscriptionType = 'updates', array $criteria = [], array $options = []): Subscription
    {
        EngagementModelGuard::assertContract($subscriber, CanInteract::class, 'subscriber');

        if ($subject !== null) {
            EngagementModelGuard::assertContract($subject, Subscribable::class, 'subject');
        }

        $subscriptionType = EngagementModelGuard::requiredString($subscriptionType, 'subscription_type');
        $criteria = SubscriptionCriteria::normalize(EngagementModelGuard::optionalArray($criteria, 'criteria') ?? []);
        $notificationLevel = EngagementModelGuard::boundedString($options['notification_level'] ?? null, 'notification_level');
        $source = EngagementModelGuard::boundedString($options['source'] ?? null, 'source');
        $metadata = EngagementModelGuard::optionalArray($options['metadata'] ?? null, 'metadata');

        if (! $this->policy->canSubscribe($subscriber, $subject, $subscriptionType)) {
            throw new AuthorizationException('Subscribing to this subject is not authorized.');
        }

        $subscriberIdentity = EngagementModelGuard::identity($subscriber, 'subscriber');
        $subjectIdentity = $subject === null ? null : EngagementModelGuard::identity($subject, 'subject');

        try {
            return (new Subscription)->getConnection()->transaction(function () use ($subscriber, $subject, $subscriptionType, $criteria, $notificationLevel, $source, $metadata, $subscriberIdentity, $subjectIdentity): Subscription {
                $existing = $this->findMatchingSubscription($subscriber, $subject, $subscriptionType, $criteria, null, true);

                if ($existing !== null) {
                    if ($existing->status === SubscriptionStatus::Active
                        && ($existing->expires_at === null || $existing->expires_at->isFuture())) {
                        return $existing;
                    }

                    $existing->update([
                        'status' => SubscriptionStatus::Active,
                        'criteria' => $criteria,
                        'unsubscribed_at' => null,
                        'muted_at' => null,
                        'expires_at' => null,
                        'subscribed_at' => CarbonImmutable::now(),
                        'notification_level' => $notificationLevel,
                        'source' => $source,
                        'metadata' => $metadata,
                    ]);
                    event(new SubscriptionCreated($existing));

                    return $existing;
                }

                $subscription = Subscription::query()->create([
                    'subscriber_type' => $subscriberIdentity['type'],
                    'subscriber_id' => $subscriberIdentity['id'],
                    'subscribable_type' => $subjectIdentity['type'] ?? null,
                    'subscribable_id' => $subjectIdentity['id'] ?? null,
                    'subscription_type' => $subscriptionType,
                    'criteria' => $criteria,
                    'status' => SubscriptionStatus::Active,
                    'notification_level' => $notificationLevel,
                    'subscribed_at' => CarbonImmutable::now(),
                    'source' => $source,
                    'metadata' => $metadata,
                ]);

                event(new SubscriptionCreated($subscription));

                return $subscription;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = $this->findMatchingSubscription($subscriber, $subject, $subscriptionType, $criteria);

            if ($existing instanceof Subscription) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function unsubscribe(CanInteract $subscriber, ?Subscribable $subject = null, string $subscriptionType = 'updates', array $criteria = []): void
    {
        EngagementModelGuard::assertContract($subscriber, CanInteract::class, 'subscriber');

        if ($subject !== null) {
            EngagementModelGuard::assertContract($subject, Subscribable::class, 'subject');
        }

        $subscriptionType = EngagementModelGuard::requiredString($subscriptionType, 'subscription_type');
        $criteria = SubscriptionCriteria::normalize(EngagementModelGuard::optionalArray($criteria, 'criteria') ?? []);

        (new Subscription)->getConnection()->transaction(function () use ($subscriber, $subject, $subscriptionType, $criteria): void {
            $subscription = $this->findMatchingSubscription($subscriber, $subject, $subscriptionType, $criteria, null, true);

            if ($subscription === null || ! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Muted], true)) {
                return;
            }

            $subscription->update([
                'status' => SubscriptionStatus::Unsubscribed,
                'unsubscribed_at' => CarbonImmutable::now(),
                'muted_at' => null,
            ]);
            event(new SubscriptionCancelled($subscription));
        });
    }

    public function muteSubscription(Subscription $subscription): Subscription
    {
        return $this->transition($subscription, SubscriptionStatus::Active, SubscriptionStatus::Muted);
    }

    public function unmuteSubscription(Subscription $subscription): Subscription
    {
        return $this->transition($subscription, SubscriptionStatus::Muted, SubscriptionStatus::Active);
    }

    private function transition(Subscription $subscription, SubscriptionStatus $from, SubscriptionStatus $to): Subscription
    {
        return $subscription->getConnection()->transaction(function () use ($subscription, $from, $to): Subscription {
            if (Subscription::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Subscription::class, $subscription->getKey());
            }

            $locked = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== $from || ($locked->expires_at !== null && ! $locked->expires_at->isFuture())) {
                return $locked;
            }

            $locked->update([
                'status' => $to,
                'muted_at' => $to === SubscriptionStatus::Muted ? CarbonImmutable::now() : null,
            ]);
            event($to === SubscriptionStatus::Muted ? new SubscriptionMuted($locked) : new SubscriptionUnmuted($locked));

            return $locked;
        });
    }

    public function matchingSubscriptions(Subscribable $subject, string $trigger, array $context = []): iterable
    {
        EngagementModelGuard::assertContract($subject, Subscribable::class, 'subject');

        $subjectIdentity = EngagementModelGuard::identity($subject, 'subject');
        $subjectType = $subjectIdentity['type'];
        $subjectId = $subjectIdentity['id'];
        $context = SubscriptionCriteria::normalize($context);

        $subscriptions = Subscription::query()
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', CarbonImmutable::now());
            })
            ->where(function (Builder $query) use ($subjectType, $subjectId): void {
                $query
                    ->where(function (Builder $q) use ($subjectType, $subjectId): void {
                        $q->where('subscribable_type', $subjectType)
                            ->where('subscribable_id', $subjectId);
                    })
                    ->orWhere(function (Builder $q): void {
                        $q->whereNull('subscribable_type')
                            ->whereNull('subscribable_id');
                    });
            })
            ->lazyById(100);

        foreach ($subscriptions as $subscription) {
            if (! $this->criteriaMatches($subscription->criteria ?? [], $context)) {
                continue;
            }

            event(new SubscriptionMatched($subscription, $subject, $trigger));

            yield $subscription;
        }
    }

    private function findMatchingSubscription(
        CanInteract $subscriber,
        ?Subscribable $subject,
        string $subscriptionType,
        array $criteria,
        ?string $status = null,
        bool $lock = false,
    ): ?Subscription {
        $query = Subscription::query()
            ->where('subscriber_type', $this->morphClass($subscriber))
            ->where('subscriber_id', $this->morphKey($subscriber))
            ->where('subscription_type', $subscriptionType)
            ->where('criteria_hash', SubscriptionCriteria::hash($criteria));

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($subject !== null) {
            $query
                ->where('subscribable_type', $this->morphClass($subject))
                ->where('subscribable_id', $this->morphKey($subject));
        } else {
            $query->whereNull('subscribable_type')
                ->whereNull('subscribable_id');
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        foreach ($query->get() as $subscription) {
            if ($this->criteriaEquals($subscription->criteria ?? [], $criteria)) {
                return $subscription;
            }
        }

        return null;
    }

    private function morphClass(object $model): string
    {
        EngagementModelGuard::assertModel($model, 'subscription model');

        /** @var Model $model */
        return $model->getMorphClass();
    }

    private function morphKey(object $model): string
    {
        EngagementModelGuard::assertModel($model, 'subscription model');

        /** @var Model $model */
        return (string) $model->getKey();
    }

    private function criteriaEquals(array $left, array $right): bool
    {
        return SubscriptionCriteria::normalize($left) === SubscriptionCriteria::normalize($right);
    }

    /**
     * @param  array<string|int, mixed>  $criteria
     * @param  array<string|int, mixed>  $context
     */
    private function criteriaMatches(array $criteria, array $context): bool
    {
        foreach ($criteria as $key => $value) {
            if (! array_key_exists($key, $context)) {
                return false;
            }

            $contextValue = $context[$key];

            if (is_array($value) && is_array($contextValue)) {
                if (! $this->criteriaMatches($value, $contextValue)) {
                    return false;
                }

                continue;
            }

            if ($contextValue !== $value) {
                return false;
            }
        }

        return true;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true);
    }
}
