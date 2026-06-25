<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Services;

use AIArmada\Engagement\Contracts\EngagementPolicyResolver;
use AIArmada\Engagement\Contracts\SubscriptionManager;
use AIArmada\Engagement\Events\SubscriptionCancelled;
use AIArmada\Engagement\Events\SubscriptionCreated;
use AIArmada\Engagement\Events\SubscriptionMatched;
use AIArmada\Engagement\Events\SubscriptionMuted;
use AIArmada\Engagement\Events\SubscriptionUnmuted;
use AIArmada\Engagement\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class DefaultSubscriptionManager implements SubscriptionManager
{
    public function __construct(
        private readonly EngagementPolicyResolver $policy,
    ) {}

    public function subscribe(mixed $subscriber, mixed $subject = null, string $subscriptionType = 'updates', array $criteria = [], array $options = []): Subscription
    {
        if (! $this->policy->canSubscribe($subscriber, $subject, $subscriptionType)) {
            throw new AuthorizationException('Subscribing to this subject is not authorized.');
        }

        $criteria = $this->normalizeCriteria($criteria);

        $existing = $this->findMatchingSubscription($subscriber, $subject, $subscriptionType, $criteria);

        if ($existing !== null) {
            if ($existing->status === Subscription::STATUS_ACTIVE) {
                return $existing;
            }

            $existing->update([
                'status' => Subscription::STATUS_ACTIVE,
                'criteria' => $criteria,
                'unsubscribed_at' => null,
                'muted_at' => null,
                'subscribed_at' => CarbonImmutable::now(),
                'source' => $options['source'] ?? null,
                'metadata' => $options['metadata'] ?? null,
            ]);
            event(new SubscriptionCreated($existing));

            return $existing;
        }

        $subscription = Subscription::query()->create([
            'subscriber_type' => $subscriber->getMorphClass(),
            'subscriber_id' => $subscriber->getKey(),
            'subscribable_type' => $subject?->getMorphClass(),
            'subscribable_id' => $subject?->getKey(),
            'subscription_type' => $subscriptionType,
            'criteria' => $criteria,
            'status' => 'active',
            'notification_level' => $options['notification_level'] ?? null,
            'subscribed_at' => CarbonImmutable::now(),
            'source' => $options['source'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);

        event(new SubscriptionCreated($subscription));

        return $subscription;
    }

    public function unsubscribe(mixed $subscriber, mixed $subject = null, string $subscriptionType = 'updates', array $criteria = []): void
    {
        $subscription = $this->findMatchingSubscription(
            $subscriber,
            $subject,
            $subscriptionType,
            $this->normalizeCriteria($criteria),
            Subscription::STATUS_ACTIVE,
        );

        if ($subscription !== null) {
            $subscription->update(['status' => 'unsubscribed', 'unsubscribed_at' => CarbonImmutable::now()]);
            event(new SubscriptionCancelled($subscription));
        }
    }

    public function muteSubscription(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => 'muted', 'muted_at' => CarbonImmutable::now()]);
        event(new SubscriptionMuted($subscription));

        return $subscription;
    }

    public function unmuteSubscription(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => Subscription::STATUS_ACTIVE,
            'muted_at' => null,
        ]);
        event(new SubscriptionUnmuted($subscription));

        return $subscription;
    }

    public function matchingSubscriptions(mixed $subject, string $trigger, array $context = []): iterable
    {
        $subjectType = $subject->getMorphClass();
        $subjectId = $subject->getKey();
        $context = $this->normalizeCriteria($context);

        $subscriptions = Subscription::query()
            ->where('status', 'active')
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
            ->cursor();

        foreach ($subscriptions as $subscription) {
            if (! $this->criteriaMatches($subscription->criteria ?? [], $context)) {
                continue;
            }

            event(new SubscriptionMatched($subscription, $subject, $trigger));

            yield $subscription;
        }
    }

    private function findMatchingSubscription(
        mixed $subscriber,
        mixed $subject,
        string $subscriptionType,
        array $criteria,
        ?string $status = null,
    ): ?Subscription {
        $query = Subscription::query()
            ->where('subscriber_type', $this->morphClass($subscriber))
            ->where('subscriber_id', $this->morphKey($subscriber))
            ->where('subscription_type', $subscriptionType);

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

        foreach ($query->get() as $subscription) {
            if ($this->criteriaEquals($subscription->criteria ?? [], $criteria)) {
                return $subscription;
            }
        }

        return null;
    }

    private function morphClass(mixed $model): string
    {
        if (! is_object($model) || ! method_exists($model, 'getMorphClass')) {
            throw new InvalidArgumentException('Subscriptions require morphable subscriber and subject models.');
        }

        return $model->getMorphClass();
    }

    private function morphKey(mixed $model): string
    {
        if (! is_object($model) || ! method_exists($model, 'getKey')) {
            throw new InvalidArgumentException('Subscriptions require morphable subscriber and subject models.');
        }

        return (string) $model->getKey();
    }

    /**
     * @param  array<string|int, mixed>  $data
     * @return array<string|int, mixed>
     */
    private function normalizeCriteria(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalizeCriteria($value);
            }
        }

        if ($this->isAssociativeArray($data)) {
            ksort($data);
        }

        return $data;
    }

    private function criteriaEquals(array $left, array $right): bool
    {
        return $this->normalizeCriteria($left) === $this->normalizeCriteria($right);
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

    /**
     * @param  array<string|int, mixed>  $data
     */
    private function isAssociativeArray(array $data): bool
    {
        return $data !== [] && array_keys($data) !== range(0, count($data) - 1);
    }
}
