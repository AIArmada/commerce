<?php

declare(strict_types=1);

use AIArmada\CashierChip\Checkout;
use AIArmada\CashierChip\CheckoutBuilder;
use AIArmada\CashierChip\Payment;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

uses(CashierChipTestCase::class);

beforeEach(function (): void {
    $this->user = $this->createUser([
        'chip_id' => 'test-client-id',
    ]);
});

function createCheckoutPurchaseData(array $overrides = []): array
{
    return array_merge([
        'id' => 'test-purchase-id',
        'checkout_url' => 'https://chip.com/checkout/test-purchase-id',
        'status' => 'created',
        'purchase' => [
            'total' => 10000,
            'currency' => 'MYR',
        ],
    ], $overrides);
}

it('can create checkout instance from owner and purchase data', function (): void {
    $checkout = new Checkout($this->user, PurchaseData::from(createCheckoutPurchaseData()));

    expect($checkout)->toBeInstanceOf(Checkout::class);
});

it('can create checkout for guest', function (): void {
    $builder = Checkout::guest();

    expect($builder)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can create checkout for customer', function (): void {
    $builder = Checkout::customer($this->user);

    expect($builder)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can get checkout url', function (): void {
    $checkout = new Checkout($this->user, PurchaseData::from(createCheckoutPurchaseData()));

    expect($checkout->url())->toBe('https://chip.com/checkout/test-purchase-id');
});

it('can get purchase id', function (): void {
    $checkout = new Checkout($this->user, PurchaseData::from(createCheckoutPurchaseData()));

    expect($checkout->id())->toBe('test-purchase-id');
});

it('can get owner', function (): void {
    $checkout = new Checkout($this->user, PurchaseData::from(createCheckoutPurchaseData()));

    expect($checkout->owner())->toBe($this->user);
});

it('can get chip purchase data', function (): void {
    $checkout = new Checkout($this->user, PurchaseData::from(createCheckoutPurchaseData()));

    expect($checkout->asChipPurchase())->toBeInstanceOf(PurchaseData::class);
    expect($checkout->asChipPurchase()->id)->toBe('test-purchase-id');
});

it('can convert to payment instance', function (): void {
    $checkout = new Checkout($this->user, PurchaseData::from(createCheckoutPurchaseData()));

    expect($checkout->asPayment())->toBeInstanceOf(Payment::class);
});

it('can convert to array', function (): void {
    $checkout = new Checkout($this->user, PurchaseData::from(createCheckoutPurchaseData()));

    expect($checkout->toArray())->toBeArray();
    expect($checkout->toArray()['id'])->toBe('test-purchase-id');
});

it('can serialize to json', function (): void {
    $checkout = new Checkout($this->user, PurchaseData::from(createCheckoutPurchaseData()));

    expect($checkout->toJson())->toBeString();
    expect(json_decode($checkout->toJson(), true)['id'])->toBe('test-purchase-id');
});

it('can create redirect response', function (): void {
    $checkout = new Checkout($this->user, PurchaseData::from(createCheckoutPurchaseData()));

    $response = $checkout->redirect();

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getTargetUrl())->toBe('https://chip.com/checkout/test-purchase-id');
});

it('can access purchase data via magic getter', function (): void {
    $checkout = new Checkout($this->user, PurchaseData::from(createCheckoutPurchaseData()));

    expect($checkout->id)->toBe('test-purchase-id');
    expect($checkout->status)->toBe('created');
});

it('returns null for missing properties via magic getter', function (): void {
    $checkout = new Checkout($this->user, PurchaseData::from(createCheckoutPurchaseData()));

    expect($checkout->nonexistent)->toBeNull();
});

it('can be json serialized', function (): void {
    $checkout = new Checkout($this->user, PurchaseData::from(createCheckoutPurchaseData()));

    expect($checkout->jsonSerialize())->toBeArray();
    expect($checkout->jsonSerialize()['id'])->toBe('test-purchase-id');
});

it('can be used as a response', function (): void {
    $checkout = new Checkout($this->user, PurchaseData::from(createCheckoutPurchaseData()));

    $request = request();
    $response = $checkout->toResponse($request);

    expect($response)->toBeInstanceOf(Response::class);
});

it('can create checkout without owner', function (): void {
    $checkout = new Checkout(null, PurchaseData::from(createCheckoutPurchaseData()));

    expect($checkout->owner())->toBeNull();
    expect($checkout->id())->toBe('test-purchase-id');
});
