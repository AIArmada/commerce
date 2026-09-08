<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;

uses(CashierChipTestCase::class);

it('can set custom customer model', function (): void {
    Cashier::useCustomerModel(User::class);

    expect(Cashier::$customerModel)->toBe(User::class);
});

it('can set custom subscription model', function (): void {
    Cashier::useSubscriptionModel(Subscription::class);

    expect(Cashier::$subscriptionModel)->toBe(Subscription::class);
});

it('can set custom subscription item model', function (): void {
    Cashier::useSubscriptionItemModel(SubscriptionItem::class);

    expect(Cashier::$subscriptionItemModel)->toBe(SubscriptionItem::class);
});

it('can keep past due subscriptions active', function (): void {
    Cashier::keepPastDueSubscriptionsActive();

    expect(Cashier::$deactivatePastDue)->toBeFalse();

    // Reset
    Cashier::$deactivatePastDue = true;
});

it('can keep incomplete subscriptions active', function (): void {
    Cashier::keepIncompleteSubscriptionsActive();

    expect(Cashier::$deactivateIncomplete)->toBeFalse();

    // Reset
    Cashier::$deactivateIncomplete = true;
});

it('can ignore routes', function (): void {
    Cashier::ignoreRoutes();

    expect(Cashier::$registersRoutes)->toBeFalse();

    // Reset
    Cashier::$registersRoutes = true;
});

it('restores boot-time static configuration between requests', function (): void {
    expect(Cashier::isFake())->toBeTrue();

    Cashier::ignoreRoutes();
    Cashier::keepPastDueSubscriptionsActive();
    Cashier::keepIncompleteSubscriptionsActive();
    Cashier::useCustomerModel(Subscription::class);
    Cashier::useSubscriptionModel(User::class);
    Cashier::useSubscriptionItemModel(User::class);
    Cashier::restoreOctaneDefaults();

    expect(Cashier::$registersRoutes)->toBeTrue()
        ->and(Cashier::$deactivatePastDue)->toBeTrue()
        ->and(Cashier::$deactivateIncomplete)->toBeTrue()
        ->and(Cashier::$customerModel)->toBe(User::class)
        ->and(Cashier::$subscriptionModel)->toBe(Subscription::class)
        ->and(Cashier::$subscriptionItemModel)->toBe(SubscriptionItem::class)
        ->and(Cashier::isFake())->toBeFalse()
        ->and(Cashier::getFake())->toBeNull();
});
