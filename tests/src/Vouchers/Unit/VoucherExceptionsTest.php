<?php

declare(strict_types=1);

use AIArmada\Vouchers\Exceptions\InvalidVoucherException;
use AIArmada\Vouchers\Exceptions\ManualRedemptionNotAllowedException;
use AIArmada\Vouchers\Exceptions\VoucherException;
use AIArmada\Vouchers\Exceptions\VoucherExpiredException;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Exceptions\VoucherUsageLimitException;

it('can create invalid voucher exceptions', function (): void {
    $exception = InvalidVoucherException::notActive('TEST10');
    expect($exception)->toBeInstanceOf(InvalidVoucherException::class)
        ->and($exception->getMessage())->toBe("Voucher 'TEST10' is not active.");

    $exception = InvalidVoucherException::notStarted('TEST10');
    expect($exception)->toBeInstanceOf(InvalidVoucherException::class)
        ->and($exception->getMessage())->toBe("Voucher 'TEST10' is not yet available.");

    $exception = InvalidVoucherException::minCartValue('TEST10', '100.00');
    expect($exception)->toBeInstanceOf(InvalidVoucherException::class)
        ->and($exception->getMessage())->toBe("Voucher 'TEST10' requires a minimum cart value of 100.00.");
});

it('can create voucher expired exception', function (): void {
    $exception = VoucherExpiredException::withCode('TEST10');
    expect($exception)->toBeInstanceOf(VoucherExpiredException::class)
        ->and($exception->getMessage())->toBe("Voucher 'TEST10' has expired.");
});

it('can create voucher not found exception', function (): void {
    $exception = VoucherNotFoundException::withCode('TEST10');
    expect($exception)->toBeInstanceOf(VoucherNotFoundException::class)
        ->and($exception->getMessage())->toBe("Voucher with code 'TEST10' not found.");
});

it('can create voucher usage limit exception', function (): void {
    $exception = VoucherUsageLimitException::globalLimit('TEST10');
    expect($exception)->toBeInstanceOf(VoucherUsageLimitException::class)
        ->and($exception->getMessage())->toBe("Voucher 'TEST10' has reached its usage limit.");

    $exception = VoucherUsageLimitException::userLimit('TEST10');
    expect($exception)->toBeInstanceOf(VoucherUsageLimitException::class)
        ->and($exception->getMessage())->toBe("You have already used voucher 'TEST10' the maximum number of times.");
});

it('can create manual redemption not allowed exception', function (): void {
    $exception = new ManualRedemptionNotAllowedException('TEST10');
    expect($exception)->toBeInstanceOf(ManualRedemptionNotAllowedException::class)
        ->and($exception->getMessage())->toBe('TEST10');
});

it('can create base voucher exception', function (): void {
    $exception = new VoucherException('Test message');
    expect($exception)->toBeInstanceOf(VoucherException::class)
        ->and($exception->getMessage())->toBe('Test message');
});