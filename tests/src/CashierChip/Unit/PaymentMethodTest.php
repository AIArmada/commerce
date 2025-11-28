<?php

declare(strict_types=1);

use AIArmada\CashierChip\PaymentMethod;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;

beforeEach(function () {
    $this->owner = new User([
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'default_pm_id' => 'test-recurring-token',
    ]);

    $this->recurringTokenData = [
        'id' => 'test-recurring-token',
        'recurring_token' => 'test-recurring-token',
        'card_brand' => 'Visa',
        'brand' => 'Visa',
        'last_4' => '4242',
        'card_last_4' => '4242',
        'exp_month' => 12,
        'exp_year' => 2025,
        'type' => 'card',
    ];
});

it('can get recurring token id', function () {
    $paymentMethod = new PaymentMethod($this->owner, $this->recurringTokenData);
    
    expect($paymentMethod->id())->toBe('test-recurring-token');
});

it('can get card brand', function () {
    $paymentMethod = new PaymentMethod($this->owner, $this->recurringTokenData);
    
    expect($paymentMethod->brand())->toBe('Visa');
});

it('can get last four digits', function () {
    $paymentMethod = new PaymentMethod($this->owner, $this->recurringTokenData);
    
    expect($paymentMethod->lastFour())->toBe('4242');
});

it('can get expiration month', function () {
    $paymentMethod = new PaymentMethod($this->owner, $this->recurringTokenData);
    
    expect($paymentMethod->expirationMonth())->toBe(12);
});

it('can get expiration year', function () {
    $paymentMethod = new PaymentMethod($this->owner, $this->recurringTokenData);
    
    expect($paymentMethod->expirationYear())->toBe(2025);
});

it('can get payment method type', function () {
    $paymentMethod = new PaymentMethod($this->owner, $this->recurringTokenData);
    
    expect($paymentMethod->type())->toBe('card');
});

it('can check if default payment method', function () {
    // For isDefault() to work, the owner needs to have a defaultPaymentMethod() method
    // that returns a PaymentMethod instance. Since our mock User doesn't have this,
    // we create a mock that returns null (meaning this is not the default)
    $paymentMethod = new PaymentMethod($this->owner, $this->recurringTokenData);
    
    // The method should return false since mock User->defaultPaymentMethod() returns null
    expect($paymentMethod->isDefault())->toBeFalse();
});

it('can get owner', function () {
    $paymentMethod = new PaymentMethod($this->owner, $this->recurringTokenData);
    
    expect($paymentMethod->owner())->toBe($this->owner);
});

it('can get as chip recurring token', function () {
    $paymentMethod = new PaymentMethod($this->owner, $this->recurringTokenData);
    
    expect($paymentMethod->asChipRecurringToken())->toBeArray();
    expect($paymentMethod->asChipRecurringToken()['id'])->toBe('test-recurring-token');
});

it('can convert to array', function () {
    $paymentMethod = new PaymentMethod($this->owner, $this->recurringTokenData);
    
    expect($paymentMethod->toArray())->toBeArray();
    expect($paymentMethod->toArray()['id'])->toBe('test-recurring-token');
});

it('can convert to json', function () {
    $paymentMethod = new PaymentMethod($this->owner, $this->recurringTokenData);
    
    expect($paymentMethod->toJson())->toBeString();
});

it('can dynamically access properties', function () {
    $paymentMethod = new PaymentMethod($this->owner, $this->recurringTokenData);
    
    expect($paymentMethod->id)->toBe('test-recurring-token');
    expect($paymentMethod->type)->toBe('card');
});
