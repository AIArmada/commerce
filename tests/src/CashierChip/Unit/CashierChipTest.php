<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Subscription\Subscription;
use AIArmada\CashierChip\Subscription\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;

uses(CashierChipTestCase::class);

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
