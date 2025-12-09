<?php

declare(strict_types=1);

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;

uses(CashierChipTestCase::class);

it('can format amount with default currency', function (): void {
    $formatted = Cashier::formatAmount(10000);

    expect($formatted)->toBeString();
    // 10000 cents = 100.00 in main currency unit
    // The format may be like "RM 100.00" or "$100.00"
    expect($formatted)->toMatch('/\d+/');
});

it('can format amount with specific currency', function (): void {
    $formatted = Cashier::formatAmount(10000, 'USD', 'en_US');

    expect($formatted)->toBeString();
    // 10000 cents = 100.00 USD
    expect($formatted)->toMatch('/\d+/');
});

it('can use custom currency formatter', function (): void {
    Cashier::formatCurrencyUsing(function ($amount, $currency) {
        return 'CUSTOM: ' . $amount . ' ' . $currency;
    });

    $formatted = Cashier::formatAmount(10000, 'MYR');

    expect($formatted)->toBe('CUSTOM: 10000 MYR');

    // Reset formatter - use a no-op function instead of null
    Cashier::formatCurrencyUsing(function ($amount, $currency, $locale, $options) {
        return (new Akaunting\Money\Money($amount, new Akaunting\Money\Currency($currency ?? 'MYR'), true))->format($locale ?? 'en_US');
    });
});

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
