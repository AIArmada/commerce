<?php

declare(strict_types=1);

use AIArmada\Cashier\Drivers\Chip\ChipCheckoutBuilder;
use AIArmada\Cashier\Drivers\ChipGateway;
use AIArmada\Cashier\Drivers\Stripe\StripeCheckoutBuilder;
use AIArmada\Cashier\Drivers\Stripe\StripeSubscriptionBuilder;
use AIArmada\Cashier\Drivers\StripeGateway;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use AIArmada\Commerce\Tests\Cashier\Fixtures\OwnerScopedBillableUser;
use AIArmada\Commerce\Tests\Cashier\Fixtures\Tenant;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;

uses(CashierTestCase::class);

it('rejects a foreign billable at direct gateway entry points before provider calls', function (string $driver): void {
    config()->set('cashier.tests.owner.enabled', true);

    $owner = Tenant::query()->create(['name' => 'Billing owner']);
    $other = Tenant::query()->create(['name' => 'Foreign owner']);

    $billable = OwnerContext::withOwner($owner, fn () => OwnerScopedBillableUser::query()->create([
        'name' => 'Billing customer',
        'email' => 'gateway-scope@example.com',
    ]));

    $gateway = new $driver;

    OwnerContext::withOwner($other, function () use ($gateway, $billable): void {
        foreach ([
            fn () => $gateway->charge($billable, 1000),
            fn () => $gateway->customer($billable),
            fn () => $gateway->createCustomer($billable),
            fn () => $gateway->updateCustomer($billable),
            fn () => $gateway->syncCustomer($billable),
            fn () => $gateway->subscriptions($billable),
            fn () => $gateway->invoices($billable),
            fn () => $gateway->paymentMethods($billable),
            fn () => $gateway->defaultPaymentMethod($billable),
            fn () => $gateway->findPaymentMethod($billable, 'method-a'),
            fn () => $gateway->createSetupIntent($billable),
            fn () => $gateway->checkout($billable),
            fn () => $gateway->subscription($billable, 'default'),
            fn () => $gateway->customerPortalUrl($billable, 'https://example.test'),
        ] as $operation) {
            expect($operation)->toThrow(AuthorizationException::class);
        }
    });
})->with([StripeGateway::class, ChipGateway::class]);

it('rechecks ownership when a checkout or subscription builder is executed', function (): void {
    config()->set('cashier.tests.owner.enabled', true);

    $owner = Tenant::query()->create(['name' => 'Builder owner']);
    $other = Tenant::query()->create(['name' => 'Foreign owner']);

    $billable = OwnerContext::withOwner($owner, fn () => OwnerScopedBillableUser::query()->create([
        'name' => 'Billing customer',
        'email' => 'builder-scope@example.com',
    ]));

    $stripe = new StripeCheckoutBuilder(new StripeGateway, $billable);
    $chip = new ChipCheckoutBuilder(new ChipGateway, $billable);
    $subscription = new StripeSubscriptionBuilder($billable, 'default');

    OwnerContext::withOwner($other, function () use ($stripe, $chip, $subscription): void {
        expect(fn () => $stripe->create())->toThrow(AuthorizationException::class)
            ->and(fn () => $chip->create())->toThrow(AuthorizationException::class)
            ->and(fn () => $subscription->create())->toThrow(AuthorizationException::class)
            ->and(fn () => $subscription->checkout())->toThrow(AuthorizationException::class);
    });
});
