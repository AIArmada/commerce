<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Events\SubscriptionCanceled;
use Lorisleiva\Actions\Concerns\AsAction;

final class CancelSubscription
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $options
     */
    public function handle(SubscriptionContract $subscription, bool $immediate = false, array $options = []): SubscriptionContract
    {
        if ($immediate) {
            $subscription->cancelNow();
        } else {
            $subscription->cancel();
        }

        SubscriptionCanceled::dispatch($subscription);

        return $subscription;
    }
}
