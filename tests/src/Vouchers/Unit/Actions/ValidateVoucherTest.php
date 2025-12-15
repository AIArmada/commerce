<?php

declare(strict_types=1);

use AIArmada\Vouchers\Actions\ValidateVoucher;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Services\VoucherValidator;
use Lorisleiva\Actions\Concerns\AsAction;

describe('ValidateVoucher action', function (): void {
    it('uses AsAction trait', function (): void {
        $traits = class_uses(ValidateVoucher::class);

        expect($traits)->toContain(AsAction::class);
    });

    it('can be instantiated with VoucherValidator', function (): void {
        $mockValidator = Mockery::mock(VoucherValidator::class);
        $action = new ValidateVoucher($mockValidator);

        expect($action)->toBeInstanceOf(ValidateVoucher::class);
    });

    it('delegates to VoucherValidator service', function (): void {
        $mockValidator = Mockery::mock(VoucherValidator::class);
        $mockValidator->shouldReceive('validate')
            ->once()
            ->with('TESTCODE', ['total' => 1000])
            ->andReturn(VoucherValidationResult::valid());

        $action = new ValidateVoucher($mockValidator);
        $result = $action->handle('TESTCODE', ['total' => 1000]);

        expect($result)->toBeInstanceOf(VoucherValidationResult::class);
        expect($result->isValid)->toBeTrue();
    });

    it('returns invalid result from validator', function (): void {
        $mockValidator = Mockery::mock(VoucherValidator::class);
        $mockValidator->shouldReceive('validate')
            ->once()
            ->with('BADCODE', [])
            ->andReturn(VoucherValidationResult::invalid('Voucher not found.'));

        $action = new ValidateVoucher($mockValidator);
        $result = $action->handle('BADCODE', []);

        expect($result->isValid)->toBeFalse();
        expect($result->reason)->toBe('Voucher not found.');
    });

    it('passes cart to validator', function (): void {
        $cart = ['total' => 5000, 'items' => []];
        $mockValidator = Mockery::mock(VoucherValidator::class);
        $mockValidator->shouldReceive('validate')
            ->once()
            ->with('CODE', $cart)
            ->andReturn(VoucherValidationResult::valid());

        $action = new ValidateVoucher($mockValidator);
        $result = $action->handle('CODE', $cart);

        expect($result->isValid)->toBeTrue();
    });

    it('can be called via run static method', function (): void {
        // Verify the run method exists from AsAction
        expect(method_exists(ValidateVoucher::class, 'run'))->toBeTrue();
    });

    it('has handle method', function (): void {
        expect(method_exists(ValidateVoucher::class, 'handle'))->toBeTrue();
    });
});

afterEach(function (): void {
    Mockery::close();
});
