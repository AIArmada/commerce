<?php

declare(strict_types=1);

use AIArmada\CashierChip\CashierChip;
use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionItem;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;

uses(CashierChipTestCase::class);

it('can format amount with default currency', function () {
    $formatted = CashierChip::formatAmount(10000);
    
    expect($formatted)->toBeString();
    // 10000 cents = 100.00 in main currency unit
    // The format may be like "RM 100.00" or "$100.00"
    expect($formatted)->toMatch('/\d+/');
});

it('can format amount with specific currency', function () {
    $formatted = CashierChip::formatAmount(10000, 'USD', 'en_US');
    
    expect($formatted)->toBeString();
    // 10000 cents = 100.00 USD
    expect($formatted)->toMatch('/\d+/');
});

it('can use custom currency formatter', function () {
    CashierChip::formatCurrencyUsing(function ($amount, $currency) {
        return 'CUSTOM: '.$amount.' '.$currency;
    });

    $formatted = CashierChip::formatAmount(10000, 'MYR');
    
    expect($formatted)->toBe('CUSTOM: 10000 MYR');

    // Reset formatter - use a no-op function instead of null
    CashierChip::formatCurrencyUsing(function ($amount, $currency, $locale, $options) {
        return (new \Akaunting\Money\Money($amount, new \Akaunting\Money\Currency($currency ?? 'MYR'), true))->format($locale ?? 'en_US');
    });
});

it('can set custom customer model', function () {
    CashierChip::useCustomerModel(User::class);
    
    expect(CashierChip::$customerModel)->toBe(User::class);
});

it('can set custom subscription model', function () {
    CashierChip::useSubscriptionModel(Subscription::class);
    
    expect(CashierChip::$subscriptionModel)->toBe(Subscription::class);
});

it('can set custom subscription item model', function () {
    CashierChip::useSubscriptionItemModel(SubscriptionItem::class);
    
    expect(CashierChip::$subscriptionItemModel)->toBe(SubscriptionItem::class);
});

it('can keep past due subscriptions active', function () {
    CashierChip::keepPastDueSubscriptionsActive();
    
    expect(CashierChip::$deactivatePastDue)->toBeFalse();
    
    // Reset
    CashierChip::$deactivatePastDue = true;
});

it('can keep incomplete subscriptions active', function () {
    CashierChip::keepIncompleteSubscriptionsActive();
    
    expect(CashierChip::$deactivateIncomplete)->toBeFalse();
    
    // Reset
    CashierChip::$deactivateIncomplete = true;
});

it('can ignore routes', function () {
    CashierChip::ignoreRoutes();
    
    expect(CashierChip::$registersRoutes)->toBeFalse();
    
    // Reset
    CashierChip::$registersRoutes = true;
});
