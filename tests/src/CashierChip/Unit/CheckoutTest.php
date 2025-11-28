<?php

declare(strict_types=1);

use AIArmada\CashierChip\Checkout;
use AIArmada\CashierChip\CheckoutBuilder;
use AIArmada\CashierChip\Payment;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

beforeEach(function () {
    $this->user = $this->createUser([
        'chip_id' => 'test-client-id',
    ]);
});

it('can create checkout instance from owner and purchase data', function () {
    $purchaseData = [
        'id' => 'test-purchase-id',
        'checkout_url' => 'https://chip.com/checkout/test-purchase-id',
        'status' => 'created',
        'total' => 100.00,
        'currency' => 'MYR',
    ];
    
    $checkout = new Checkout($this->user, $purchaseData);
    
    expect($checkout)->toBeInstanceOf(Checkout::class);
});

it('can create checkout for guest', function () {
    $builder = Checkout::guest();
    
    expect($builder)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can create checkout for customer', function () {
    $builder = Checkout::customer($this->user);
    
    expect($builder)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can get checkout url', function () {
    $purchaseData = [
        'id' => 'test-purchase-id',
        'checkout_url' => 'https://chip.com/checkout/test-purchase-id',
        'status' => 'created',
    ];
    
    $checkout = new Checkout($this->user, $purchaseData);
    
    expect($checkout->url())->toBe('https://chip.com/checkout/test-purchase-id');
});

it('can get purchase id', function () {
    $purchaseData = [
        'id' => 'test-purchase-id',
        'checkout_url' => 'https://chip.com/checkout/test-purchase-id',
    ];
    
    $checkout = new Checkout($this->user, $purchaseData);
    
    expect($checkout->id())->toBe('test-purchase-id');
});

it('can get owner', function () {
    $purchaseData = [
        'id' => 'test-purchase-id',
    ];
    
    $checkout = new Checkout($this->user, $purchaseData);
    
    expect($checkout->owner())->toBe($this->user);
});

it('can get chip purchase data', function () {
    $purchaseData = [
        'id' => 'test-purchase-id',
        'total' => 100.00,
        'currency' => 'MYR',
    ];
    
    $checkout = new Checkout($this->user, $purchaseData);
    
    expect($checkout->asChipPurchase())->toBe($purchaseData);
});

it('can convert to payment instance', function () {
    $purchaseData = [
        'id' => 'test-purchase-id',
        'status' => 'created',
        'total' => 100.00,
    ];
    
    $checkout = new Checkout($this->user, $purchaseData);
    
    expect($checkout->asPayment())->toBeInstanceOf(Payment::class);
});

it('can convert to array', function () {
    $data = [
        'id' => 'test-purchase-id',
        'checkout_url' => 'https://chip.com/checkout/test-purchase-id',
        'status' => 'created',
        'total' => 100.00,
    ];
    
    $checkout = new Checkout($this->user, $data);
    
    expect($checkout->toArray())->toBe($data);
});

it('can serialize to json', function () {
    $data = [
        'id' => 'test-purchase-id',
        'checkout_url' => 'https://chip.com/checkout/test-purchase-id',
    ];
    
    $checkout = new Checkout($this->user, $data);
    
    expect($checkout->toJson())->toBe(json_encode($data));
});

it('can create redirect response', function () {
    $checkout = new Checkout($this->user, [
        'id' => 'test-purchase-id',
        'checkout_url' => 'https://chip.com/checkout/test-purchase-id',
    ]);
    
    $response = $checkout->redirect();
    
    expect($response)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);
    expect($response->getTargetUrl())->toBe('https://chip.com/checkout/test-purchase-id');
});

it('can access purchase data via magic getter', function () {
    $checkout = new Checkout($this->user, [
        'id' => 'test-purchase-id',
        'total' => 299.99,
        'currency' => 'MYR',
    ]);
    
    expect($checkout->id)->toBe('test-purchase-id');
    expect($checkout->total)->toBe(299.99);
    expect($checkout->currency)->toBe('MYR');
});

it('returns null for missing properties via magic getter', function () {
    $checkout = new Checkout($this->user, [
        'id' => 'test-purchase-id',
    ]);
    
    expect($checkout->nonexistent)->toBeNull();
});

it('can be json serialized', function () {
    $data = [
        'id' => 'test-purchase-id',
        'checkout_url' => 'https://chip.com/checkout/test-purchase-id',
    ];
    
    $checkout = new Checkout($this->user, $data);
    
    expect($checkout->jsonSerialize())->toBe($data);
});

it('can be used as a response', function () {
    $checkout = new Checkout($this->user, [
        'id' => 'test-purchase-id',
        'checkout_url' => 'https://chip.com/checkout/test-purchase-id',
    ]);
    
    $request = request();
    $response = $checkout->toResponse($request);
    
    expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\Response::class);
});

it('can create checkout without owner', function () {
    $purchaseData = [
        'id' => 'test-purchase-id',
        'checkout_url' => 'https://chip.com/checkout/test-purchase-id',
    ];
    
    $checkout = new Checkout(null, $purchaseData);
    
    expect($checkout->owner())->toBeNull();
    expect($checkout->id())->toBe('test-purchase-id');
});
