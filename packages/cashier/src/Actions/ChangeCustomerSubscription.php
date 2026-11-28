<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Policies\SubscriptionPolicy;
use AIArmada\Cashier\Queries\FindCustomerSubscription;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final readonly class ChangeCustomerSubscription
{
    public function __construct(
        private FindCustomerSubscription $subscriptions,
        private SubscriptionPolicy $policy,
    ) {}

    public function cancel(Model $user, string $gateway, string $id): void
    {
        $subscription = $this->subscriptions->handle($user, $gateway, $id);

        if ($subscription === null || ! $this->policy->cancel($user, $subscription->original)) {
            throw new AuthorizationException('Subscription is not accessible.');
        }

        if (! $subscription->status->isCancelable() || ! method_exists($subscription->original, 'cancel')) {
            throw new RuntimeException('Subscription cannot be canceled in its current state.');
        }

        $subscription->original->cancel();
    }

    public function resume(Model $user, string $gateway, string $id): void
    {
        $subscription = $this->subscriptions->handle($user, $gateway, $id);

        if ($subscription === null || ! $this->policy->resume($user, $subscription->original)) {
            throw new AuthorizationException('Subscription is not accessible.');
        }

        if (! $subscription->status->isResumable() || ! method_exists($subscription->original, 'resume')) {
            throw new RuntimeException('Subscription cannot be resumed in its current state.');
        }

        $subscription->original->resume();
    }
}
