<?php

declare(strict_types=1);

use AIArmada\CashierChip\Payment;
use AIArmada\Chip\Data\PurchaseData;

function createPurchaseData(array $overrides = []): array
{
    return array_merge([
        'id' => 'test-purchase-id',
        'status' => 'pending',
        'checkout_url' => 'https://chip.com/checkout/test-purchase-id',
        'purchase' => [
            'total' => 10000,
            'currency' => 'MYR',
        ],
        'client' => [
            'id' => 'test-client-id',
            'email' => 'test@example.com',
        ],
        'recurring_token' => 'test-recurring-token',
    ], $overrides);
}

it('can get purchase id', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData()));

    expect($payment->id())->toBe('test-purchase-id');
});

it('can get raw amount', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData()));

    expect($payment->rawAmount())->toBe(10000);
});

it('can get currency', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData()));

    expect($payment->currency())->toBe('MYR');
});

it('can get checkout url', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData()));

    expect($payment->checkoutUrl())->toBe('https://chip.com/checkout/test-purchase-id');
});

it('can get status', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData()));

    expect($payment->status())->toBe('pending');
});

it('can check if payment is pending', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData()));

    expect($payment->isPending())->toBeTrue();
    expect($payment->isSucceeded())->toBeFalse();
});

it('can check if payment is succeeded', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData(['status' => 'success'])));

    expect($payment->isSucceeded())->toBeTrue();
    expect($payment->isPending())->toBeFalse();
});

it('can check if payment is failed', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData(['status' => 'failed'])));

    expect($payment->isFailed())->toBeTrue();
});

it('can check if payment is expired', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData(['status' => 'expired'])));

    expect($payment->isExpired())->toBeTrue();
});

it('can check if payment is cancelled', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData(['status' => 'cancelled'])));

    expect($payment->isCancelled())->toBeTrue();
});

it('can check if payment is refunded', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData(['status' => 'refunded'])));

    expect($payment->isRefunded())->toBeTrue();
});

it('can check if payment requires redirect', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData()));

    expect($payment->requiresRedirect())->toBeTrue();

    $successPayment = new Payment(PurchaseData::from(createPurchaseData(['status' => 'success'])));

    expect($successPayment->requiresRedirect())->toBeFalse();
});

it('can get recurring token', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData()));

    expect($payment->recurringToken())->toBe('test-recurring-token');
});

it('can convert to array', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData()));

    expect($payment->toArray())->toBeArray();
    expect($payment->toArray()['id'])->toBe('test-purchase-id');
});

it('can convert to json', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData()));

    expect($payment->toJson())->toBeString();
    expect(json_decode($payment->toJson(), true)['id'])->toBe('test-purchase-id');
});

it('can dynamically access properties', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData()));

    expect($payment->id)->toBe('test-purchase-id');
    expect($payment->status)->toBe('pending');
});

it('throws exception when validating pending payment', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData()));

    $payment->validate();
})->throws(AIArmada\CashierChip\Exceptions\IncompletePayment::class);

it('does not throw exception when validating successful payment', function (): void {
    $payment = new Payment(PurchaseData::from(createPurchaseData(['status' => 'success'])));

    $payment->validate();

    expect(true)->toBeTrue();
});
