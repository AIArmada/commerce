<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Actions;

use AIArmada\Cashier\Contracts\BillableContract;
use AIArmada\Cashier\Contracts\SubscriptionContract;
use AIArmada\Cashier\Events\SubscriptionCreated;
use AIArmada\Cashier\Facades\Cashier;
use AIArmada\Cashier\Gateways\AbstractGateway;
use Lorisleiva\Actions\Concerns\AsAction;

final class CreateSubscription
{
    use AsAction;

    /**
     * @param  string|array<string>  $prices
     * @param  array<string, mixed>  $options
     */
    public function handle(BillableContract $billable, string $type, string | array $prices = [], ?string $paymentMethod = null, ?string $gateway = null, array $options = []): SubscriptionContract
    {
        $gatewayName = $gateway ?? config('cashier.default', 'stripe');
        /** @var AbstractGateway $gateway */
        $gateway = Cashier::gateway($gatewayName);

        $builder = $gateway->newSubscription($billable, $type, $prices);

        $subscription = $builder->create($paymentMethod, $options);

        SubscriptionCreated::dispatch($subscription, $billable);

        return $subscription;
    }
}
