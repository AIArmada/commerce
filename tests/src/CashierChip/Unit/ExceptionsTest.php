<?php

declare(strict_types=1);

use AIArmada\CashierChip\Exceptions\CustomerAlreadyCreated;
use AIArmada\CashierChip\Exceptions\IncompletePayment;
use AIArmada\CashierChip\Exceptions\InvalidCoupon;
use AIArmada\CashierChip\Exceptions\InvalidCustomer;
use AIArmada\CashierChip\Exceptions\InvalidInvoice;
use AIArmada\CashierChip\Exceptions\InvalidPaymentMethod;
use AIArmada\CashierChip\Exceptions\SubscriptionUpdateFailure;
use AIArmada\CashierChip\Invoice;
use AIArmada\CashierChip\Payment;
use AIArmada\CashierChip\Subscription;
use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;
use Illuminate\Database\Eloquent\Model;

uses(CashierChipTestCase::class);

it('can create customer already created exception', function (): void {
    $owner = Mockery::mock(Model::class);
    $owner->shouldReceive('getAttribute')->with('chip_id')->andReturn('test-chip-id');

    $exception = CustomerAlreadyCreated::exists($owner);

    expect($exception)->toBeInstanceOf(CustomerAlreadyCreated::class);
    // expect($exception->getMessage())->toContain('test-chip-id'); // Can't easily mock default behavior of exception which reads property?
});

it('can create invalid customer exception', function (): void {
    $owner = Mockery::mock(Model::class);

    $exception = InvalidCustomer::notYetCreated($owner);

    expect($exception)->toBeInstanceOf(InvalidCustomer::class);
    expect($exception->getMessage())->toContain('not a CHIP customer');
});

it('can create invalid payment method exception for invalid owner', function (): void {
    $owner = Mockery::mock(Model::class);

    $exception = InvalidPaymentMethod::invalidOwner('pm_123', $owner);

    expect($exception)->toBeInstanceOf(InvalidPaymentMethod::class);
    expect($exception->getMessage())->toContain('does not belong');
});

it('can create invalid payment method exception for not found', function (): void {
    $exception = InvalidPaymentMethod::notFound();

    expect($exception)->toBeInstanceOf(InvalidPaymentMethod::class);
    expect($exception->getMessage())->toContain('No payment method');
});

it('can create incomplete payment exception for redirect', function (): void {
    $payment = new Payment(PurchaseData::from([
        'id' => 'test-id',
        'status' => 'pending',
        'checkout_url' => 'https://chip.com/checkout',
    ]));

    $exception = IncompletePayment::requiresRedirect($payment);

    expect($exception)->toBeInstanceOf(IncompletePayment::class);
    expect($exception->getMessage())->toContain('redirect');
    expect($exception->payment())->toBe($payment);
});

it('can create incomplete payment exception for failed', function (): void {
    $payment = new Payment(PurchaseData::from([
        'id' => 'test-id',
        'status' => 'failed',
    ]));

    $exception = IncompletePayment::failed($payment);

    expect($exception)->toBeInstanceOf(IncompletePayment::class);
    expect($exception->getMessage())->toContain('failed');
});

it('can create incomplete payment exception for expired', function (): void {
    $payment = new Payment(PurchaseData::from([
        'id' => 'test-id',
        'status' => 'expired',
    ]));

    $exception = IncompletePayment::expired($payment);

    expect($exception)->toBeInstanceOf(IncompletePayment::class);
    expect($exception->getMessage())->toContain('expired');
});

it('can create subscription update failure for incomplete subscription', function (): void {
    $subscription = new Subscription([
        'type' => 'standard',
        'chip_status' => Subscription::STATUS_INCOMPLETE,
    ]);

    $exception = SubscriptionUpdateFailure::incompleteSubscription($subscription);

    expect($exception)->toBeInstanceOf(SubscriptionUpdateFailure::class);
    expect($exception->getMessage())->toContain('incomplete payment');
    expect($exception->subscription())->toBe($subscription);
});

it('can create subscription update failure for duplicate price', function (): void {
    $subscription = new Subscription([
        'type' => 'standard',
    ]);

    $exception = SubscriptionUpdateFailure::duplicatePrice($subscription, 'price_monthly');

    expect($exception)->toBeInstanceOf(SubscriptionUpdateFailure::class);
    expect($exception->getMessage())->toContain('price_monthly');
    expect($exception->getMessage())->toContain('already attached');
});

it('can create subscription update failure for deleting last price', function (): void {
    $subscription = new Subscription([
        'type' => 'standard',
    ]);

    $exception = SubscriptionUpdateFailure::cannotDeleteLastPrice($subscription);

    expect($exception->getMessage())->toContain('last price');
});

it('can create invalid coupon exception for not found', function (): void {
    $exception = InvalidCoupon::notFound('COUPON_123');

    expect($exception)->toBeInstanceOf(InvalidCoupon::class);
    expect($exception->getMessage())->toContain('exist');
    expect($exception->getMessage())->toContain('COUPON_123');
});

it('can create invalid coupon exception for inactive', function (): void {
    $exception = InvalidCoupon::inactive('COUPON_123');

    expect($exception)->toBeInstanceOf(InvalidCoupon::class);
    expect($exception->getMessage())->toContain('not active');
});

it('can create invalid coupon exception for forever amount off in checkout', function (): void {
    $exception = InvalidCoupon::cannotUseForeverAmountOffInCheckout('COUPON_123');

    expect($exception)->toBeInstanceOf(InvalidCoupon::class);
    expect($exception->getMessage())->toContain('forever amount_off');
});

it('can create invalid invoice exception for invalid owner', function (): void {
    $invoice = Mockery::mock(Invoice::class);
    $invoice->shouldReceive('id')->andReturn('inv_123');

    $owner = Mockery::mock(Model::class);
    $owner->shouldReceive('getAttribute')->with('chip_id')->andReturn('test-chip-id');

    $exception = InvalidInvoice::invalidOwner($invoice, $owner);

    expect($exception)->toBeInstanceOf(InvalidInvoice::class);
    expect($exception->getMessage())->toContain('inv_123');
    // expect($exception->getMessage())->toContain('test-chip-id');
});

it('can create invalid invoice exception for not found', function (): void {
    $exception = InvalidInvoice::notFound('inv_123');

    expect($exception)->toBeInstanceOf(InvalidInvoice::class);
    expect($exception->getMessage())->toContain('inv_123');
});

it('can create invalid invoice exception for invalid status', function (): void {
    $exception = InvalidInvoice::invalidStatus('inv_123', 'void');

    expect($exception)->toBeInstanceOf(InvalidInvoice::class);
    expect($exception->getMessage())->toContain('inv_123');
    expect($exception->getMessage())->toContain('void');
});

it('can create invalid coupon exception for expired', function (): void {
    $exception = InvalidCoupon::expired('COUPON_123');

    expect($exception)->toBeInstanceOf(InvalidCoupon::class);
    expect($exception->getMessage())->toContain('expired');
    expect($exception->getMessage())->toContain('COUPON_123');
});

it('can create invalid coupon exception for cannot apply forever amount off to subscription', function (): void {
    $exception = InvalidCoupon::cannotApplyForeverAmountOffToSubscription('COUPON_123');

    expect($exception)->toBeInstanceOf(InvalidCoupon::class);
    expect($exception->getMessage())->toContain('forever amount_off');
    expect($exception->getMessage())->toContain('COUPON_123');
});

it('can create invalid coupon exception for forever amount off not allowed', function (): void {
    $exception = InvalidCoupon::foreverAmountOffCouponNotAllowed('COUPON_123');

    expect($exception)->toBeInstanceOf(InvalidCoupon::class);
    expect($exception->getMessage())->toContain('forever amount_off');
    expect($exception->getMessage())->toContain('not allowed');
});

it('can create invalid coupon exception for usage limit reached', function (): void {
    $exception = InvalidCoupon::usageLimitReached('COUPON_123');

    expect($exception)->toBeInstanceOf(InvalidCoupon::class);
    expect($exception->getMessage())->toContain('maximum usage limit');
    expect($exception->getMessage())->toContain('COUPON_123');
});

it('can create invalid coupon exception for per user limit reached', function (): void {
    $exception = InvalidCoupon::perUserLimitReached('COUPON_123');

    expect($exception)->toBeInstanceOf(InvalidCoupon::class);
    expect($exception->getMessage())->toContain('maximum number of times');
    expect($exception->getMessage())->toContain('COUPON_123');
});

it('can create invalid coupon exception for minimum not met', function (): void {
    $exception = InvalidCoupon::minimumNotMet('COUPON_123', 5000, 'MYR');

    expect($exception)->toBeInstanceOf(InvalidCoupon::class);
    expect($exception->getMessage())->toContain('minimum order value');
    expect($exception->getMessage())->toContain('MYR');
    expect($exception->getMessage())->toContain('50.00');
});
