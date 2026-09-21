<?php

declare(strict_types=1);

use AIArmada\CashierChip\Exceptions\CashierChipException;
use AIArmada\CashierChip\Exceptions\CustomerAlreadyCreated;
use AIArmada\CashierChip\Exceptions\IncompletePayment;
use AIArmada\CashierChip\Exceptions\InvalidCoupon;
use AIArmada\CashierChip\Exceptions\InvalidCustomer;
use AIArmada\CashierChip\Exceptions\InvalidInvoice;
use AIArmada\CashierChip\Exceptions\InvalidPaymentMethod;
use AIArmada\CashierChip\Exceptions\SubscriptionUpdateFailure;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

it('can create invalid coupon exception for minimum not met', function (): void {
    $exception = InvalidCoupon::minimumNotMet('COUPON_123', 5000, 'MYR');

    expect($exception)->toBeInstanceOf(InvalidCoupon::class);
    expect($exception->getMessage())->toContain('minimum order value');
    expect($exception->getMessage())->toContain('MYR');
    expect($exception->getMessage())->toContain('50.00');
});

test('all cashier-chip exceptions extend CashierChipException', function (): void {
    $exceptionClasses = [
        CustomerAlreadyCreated::class,
        IncompletePayment::class,
        InvalidCoupon::class,
        InvalidCustomer::class,
        InvalidInvoice::class,
        InvalidPaymentMethod::class,
        SubscriptionUpdateFailure::class,
    ];

    foreach ($exceptionClasses as $class) {
        expect(is_subclass_of($class, CashierChipException::class))->toBeTrue();
    }
});
