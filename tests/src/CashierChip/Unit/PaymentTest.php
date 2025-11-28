<?php

declare(strict_types=1);

use AIArmada\CashierChip\Payment;

beforeEach(function () {
    $this->purchaseData = [
        'id' => 'test-purchase-id',
        'status' => 'pending',
        'checkout_url' => 'https://chip.com/checkout/test-purchase-id',
        'purchase' => [
            'total' => 100.00,
            'currency' => 'MYR',
        ],
        'client' => [
            'id' => 'test-client-id',
            'email' => 'test@example.com',
        ],
        'recurring_token' => 'test-recurring-token',
    ];
});

it('can get purchase id', function () {
    $payment = new Payment($this->purchaseData);
    
    expect($payment->id())->toBe('test-purchase-id');
});

it('can get raw amount', function () {
    $payment = new Payment($this->purchaseData);
    
    // CHIP returns decimal, we convert to cents
    expect($payment->rawAmount())->toBe(10000);
});

it('can get currency', function () {
    $payment = new Payment($this->purchaseData);
    
    expect($payment->currency())->toBe('MYR');
});

it('can get checkout url', function () {
    $payment = new Payment($this->purchaseData);
    
    expect($payment->checkoutUrl())->toBe('https://chip.com/checkout/test-purchase-id');
});

it('can get status', function () {
    $payment = new Payment($this->purchaseData);
    
    expect($payment->status())->toBe('pending');
});

it('can check if payment is pending', function () {
    $payment = new Payment($this->purchaseData);
    
    expect($payment->isPending())->toBeTrue();
    expect($payment->isSucceeded())->toBeFalse();
});

it('can check if payment is succeeded', function () {
    $this->purchaseData['status'] = 'success';
    $payment = new Payment($this->purchaseData);
    
    expect($payment->isSucceeded())->toBeTrue();
    expect($payment->isPending())->toBeFalse();
});

it('can check if payment is failed', function () {
    $this->purchaseData['status'] = 'failed';
    $payment = new Payment($this->purchaseData);
    
    expect($payment->isFailed())->toBeTrue();
});

it('can check if payment is expired', function () {
    $this->purchaseData['status'] = 'expired';
    $payment = new Payment($this->purchaseData);
    
    expect($payment->isExpired())->toBeTrue();
});

it('can check if payment is cancelled', function () {
    $this->purchaseData['status'] = 'cancelled';
    $payment = new Payment($this->purchaseData);
    
    expect($payment->isCancelled())->toBeTrue();
});

it('can check if payment is refunded', function () {
    $this->purchaseData['status'] = 'refunded';
    $payment = new Payment($this->purchaseData);
    
    expect($payment->isRefunded())->toBeTrue();
});

it('can check if payment requires redirect', function () {
    $payment = new Payment($this->purchaseData);
    
    expect($payment->requiresRedirect())->toBeTrue();
    
    $this->purchaseData['status'] = 'success';
    $successPayment = new Payment($this->purchaseData);
    
    expect($successPayment->requiresRedirect())->toBeFalse();
});

it('can get recurring token', function () {
    $payment = new Payment($this->purchaseData);
    
    expect($payment->recurringToken())->toBe('test-recurring-token');
});

it('can convert to array', function () {
    $payment = new Payment($this->purchaseData);
    
    expect($payment->toArray())->toBeArray();
    expect($payment->toArray()['id'])->toBe('test-purchase-id');
});

it('can convert to json', function () {
    $payment = new Payment($this->purchaseData);
    
    expect($payment->toJson())->toBeString();
    expect(json_decode($payment->toJson(), true)['id'])->toBe('test-purchase-id');
});

it('can dynamically access properties', function () {
    $payment = new Payment($this->purchaseData);
    
    expect($payment->id)->toBe('test-purchase-id');
    expect($payment->status)->toBe('pending');
});

it('throws exception when validating pending payment', function () {
    $payment = new Payment($this->purchaseData);
    
    $payment->validate();
})->throws(\AIArmada\CashierChip\Exceptions\IncompletePayment::class);

it('does not throw exception when validating successful payment', function () {
    $this->purchaseData['status'] = 'success';
    $payment = new Payment($this->purchaseData);
    
    $payment->validate();
    
    expect(true)->toBeTrue();
});
