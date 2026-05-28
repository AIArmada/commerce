<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Policies;

use Illuminate\Database\Eloquent\Model;

/**
 * Policy for subscription authorization in the customer portal.
 *
 * Ensures users can only manage their own subscriptions.
 */
class SubscriptionPolicy
{
    /**
     * Determine whether the user can view any subscriptions.
     * In the customer portal, users can only view their own.
     */
    public function viewAny(Model $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the subscription.
     */
    public function view(Model $user, Model $subscription): bool
    {
        return $this->ownsSubscription($user, $subscription);
    }

    /**
     * Determine whether the user can cancel the subscription.
     */
    public function cancel(Model $user, Model $subscription): bool
    {
        return $this->ownsSubscription($user, $subscription);
    }

    /**
     * Determine whether the user can resume the subscription.
     */
    public function resume(Model $user, Model $subscription): bool
    {
        return $this->ownsSubscription($user, $subscription);
    }

    /**
     * Determine whether the user can update the subscription.
     */
    public function update(Model $user, Model $subscription): bool
    {
        return $this->ownsSubscription($user, $subscription);
    }

    /**
     * Determine whether the user can swap the subscription plan.
     */
    public function swap(Model $user, Model $subscription): bool
    {
        return $this->ownsSubscription($user, $subscription);
    }

    /**
     * Check if the user owns the subscription.
     */
    protected function ownsSubscription(Model $user, Model $subscription): bool
    {
        $userId = $this->resolveIdentifier($user, [$user->getKeyName()]);

        if ($userId === null) {
            return false;
        }

        $billableType = $this->resolveStringAttribute($subscription, 'billable_type');
        $billableId = $this->resolveIdentifier($subscription, ['billable_id']);

        if ($billableType !== null || $billableId !== null) {
            return $billableType === $user->getMorphClass()
                && $billableId !== null
                && (string) $billableId === (string) $userId;
        }

        $subscriptionUserId = $this->resolveIdentifier($subscription, ['user_id']);

        if ($subscriptionUserId === null) {
            return false;
        }

        return (string) $userId === (string) $subscriptionUserId;
    }

    /**
     * @param  array<int, string>  $attributeNames
     */
    private function resolveIdentifier(Model $model, array $attributeNames): int | string | null
    {
        $attributes = $model->getAttributes();

        foreach ($attributeNames as $attributeName) {
            if (! array_key_exists($attributeName, $attributes)) {
                continue;
            }

            $value = $attributes[$attributeName];

            if (is_int($value) || is_string($value)) {
                return $value;
            }
        }

        foreach ($attributeNames as $attributeName) {
            $value = $model->getRawOriginal($attributeName);

            if (is_int($value) || is_string($value)) {
                return $value;
            }
        }

        return null;
    }

    private function resolveStringAttribute(Model $model, string $attributeName): ?string
    {
        $attributes = $model->getAttributes();

        if (array_key_exists($attributeName, $attributes) && is_string($attributes[$attributeName]) && $attributes[$attributeName] !== '') {
            return $attributes[$attributeName];
        }

        $value = $model->getRawOriginal($attributeName);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
