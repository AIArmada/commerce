<?php

declare(strict_types=1);

use AIArmada\Vouchers\Exceptions\VoucherValidationException;

describe('VoucherValidationException', function (): void {
    it('can be created for a single invalid voucher', function (): void {
        $code = 'INVALID-CODE';
        $reason = 'Voucher expired';
        $exception = VoucherValidationException::invalid($code, $reason);

        expect($exception)->toBeInstanceOf(VoucherValidationException::class)
            ->and($exception->getMessage())->toContain($code)
            ->and($exception->getInvalidVouchers())->toBe([$code => $reason]);
    });

    it('can be created for multiple invalid vouchers', function (): void {
        $invalidVouchers = [
            'CODE1' => 'Expired',
            'CODE2' => 'Not applicable',
        ];

        $exception = VoucherValidationException::multipleInvalid($invalidVouchers);

        expect($exception)->toBeInstanceOf(VoucherValidationException::class)
            ->and($exception->getMessage())->toContain('CODE1')
            ->and($exception->getInvalidVouchers())->toBe($invalidVouchers);
    });
});
