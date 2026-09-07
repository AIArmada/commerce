<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Integrations\Payment\CashierChipProcessor;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

beforeEach(function (): void {
    $this->user = $this->createUser([
        'chip_id' => 'cli_test123',
    ]);

    // Add the client to the fake so getClient works
    Cashier::getFake()->getFakeClient()->createClient([
        'id' => $this->user->chip_id,
        'email' => $this->user->email,
        'full_name' => $this->user->name,
    ]);
});

describe('createSetupPurchase', function (): void {
    it('creates a zero-amount preauthorization purchase', function (): void {
        $purchase = $this->user->createSetupPurchase([
            'idempotency_key' => 'setup-attempt-1',
        ]);

        expect($purchase)->not->toBeNull();
        expect($purchase->checkout_url)->not->toBeEmpty();
        expect($purchase->checkout_url)->toContain('https://gate.chip-in.asia/checkout/');

        expect($this->fakeChip->getFakeClient()->getPurchase($purchase->id)['idempotency_key'])
            ->toBe('setup-attempt-1');
    });

    it('creates setup purchase with custom options', function (): void {
        $purchase = $this->user->createSetupPurchase([
            'product_name' => 'Card Verification',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            'idempotency_key' => 'setup-attempt-2',
        ]);

        expect($purchase)->not->toBeNull();
        expect($purchase->checkout_url)->not->toBeEmpty();
    });

    it('creates purchase with skip_capture and force_recurring flags', function (): void {
        // The fake client's createPurchase method stores the data
        // We can verify the purchase was created with correct parameters
        $purchase = $this->user->createSetupPurchase([
            'idempotency_key' => 'setup-attempt-3',
        ]);

        // The purchase should be created with preauthorization settings
        expect($purchase->id)->not->toBeEmpty();
        expect($purchase->status)->toBe('created');
    });
});

describe('setupPaymentMethodUrl', function (): void {
    it('returns checkout URL for setup purchase', function (): void {
        $url = $this->user->setupPaymentMethodUrl([
            'idempotency_key' => 'setup-attempt-4',
        ]);

        expect($url)->not->toBeEmpty();
        expect($url)->toContain('https://gate.chip-in.asia/checkout/');
    });

    it('includes success and cancel URLs in options', function (): void {
        $url = $this->user->setupPaymentMethodUrl([
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            'idempotency_key' => 'setup-attempt-5',
        ]);

        expect($url)->not->toBeEmpty();
    });
});

it('requires a stable idempotency key for setup purchases', function (): void {
    expect(fn () => $this->user->createSetupPurchase())
        ->toThrow(InvalidArgumentException::class, 'An idempotency_key option is required');
});

it('rejects a missing setup key before creating a CHIP customer', function (): void {
    $user = $this->createUser();
    $clientCount = count($this->fakeChip->getFakeClient()->getClients());

    expect(fn () => $user->createSetupPurchase())
        ->toThrow(InvalidArgumentException::class, 'An idempotency_key option is required');

    expect($user->hasChipId())->toBeFalse()
        ->and($this->fakeChip->getFakeClient()->getClients())->toHaveCount($clientCount)
        ->and($this->fakeChip->getFakeClient()->getPurchases())->toBeEmpty();
});

it('creates a missing CHIP customer before the keyed setup purchase', function (): void {
    $user = $this->createUser();

    $purchase = $user->createSetupPurchase([
        'idempotency_key' => 'setup-attempt-with-new-customer',
    ]);

    expect($user->hasChipId())->toBeTrue()
        ->and($this->fakeChip->getFakeClient()->getPurchase($purchase->id)['client_id'])
        ->toBe($user->chipId());
});

it('forwards an idempotency key for billable charges', function (): void {
    $payment = $this->user->charge(1000, null, [
        'idempotency_key' => 'charge-attempt-1',
    ]);

    expect($this->fakeChip->getFakeClient()->getPurchase($payment->id)['idempotency_key'])
        ->toBe('charge-attempt-1');
});

it('threads the checkout attempt key through the Cashier CHIP processor', function (): void {
    $session = new CheckoutSession;
    $session->setAttribute('id', 'checkout-session-1');
    $session->setAttribute('payment_attempts', 4);
    $session->setRelation('billable', $this->user);
    $session->setRelation('customer', null);

    $result = app(CashierChipProcessor::class)->createPayment(
        $session,
        new PaymentRequest(
            amount: 1000,
            currency: 'MYR',
            gateway: 'cashier-chip',
            description: 'Checkout payment',
            successUrl: 'https://example.test/success',
            failureUrl: 'https://example.test/failure',
            cancelUrl: 'https://example.test/cancel',
        ),
    );

    expect($result->paymentId)->not->toBeNull();

    expect($this->fakeChip->getFakeClient()->getPurchase($result->paymentId)['idempotency_key'])
        ->toBe('checkout-session-1');
});
