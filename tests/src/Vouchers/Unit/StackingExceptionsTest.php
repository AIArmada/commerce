<?php

declare(strict_types=1);

use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Exceptions\VoucherStackingException;
use AIArmada\Vouchers\Exceptions\VoucherValidationException;
use AIArmada\Vouchers\Stacking\StackingDecision;

describe('VoucherStackingException', function (): void {
    it('can be created from stacking decision', function (): void {
        $decision = new StackingDecision(
            allowed: false,
            reason: 'Voucher cannot be stacked',
            conflictsWith: null,
            suggestedReplacement: null
        );

        $exception = VoucherStackingException::fromDecision($decision);

        expect($exception)->toBeInstanceOf(VoucherStackingException::class)
            ->and($exception->getMessage())->toBe('Voucher cannot be stacked')
            ->and($exception->hasConflict())->toBeFalse()
            ->and($exception->hasSuggestedReplacement())->toBeFalse();
    });

    it('can be created with conflicting voucher', function (): void {
        $conflictingVoucher = Mockery::mock(VoucherCondition::class);

        $decision = new StackingDecision(
            allowed: false,
            reason: 'Conflicts with existing voucher',
            conflictsWith: $conflictingVoucher,
            suggestedReplacement: null
        );

        $exception = VoucherStackingException::fromDecision($decision);

        expect($exception->hasConflict())->toBeTrue()
            ->and($exception->getConflictingVoucher())->toBe($conflictingVoucher);
    });

    it('can be created with suggested replacement', function (): void {
        $suggestedVoucher = Mockery::mock(VoucherCondition::class);

        $decision = new StackingDecision(
            allowed: false,
            reason: 'Better voucher available',
            conflictsWith: null,
            suggestedReplacement: $suggestedVoucher
        );

        $exception = VoucherStackingException::fromDecision($decision);

        expect($exception->hasSuggestedReplacement())->toBeTrue()
            ->and($exception->getSuggestedReplacement())->toBe($suggestedVoucher);
    });

    it('creates max vouchers exceeded exception', function (): void {
        $exception = VoucherStackingException::maxVouchersExceeded(3);

        expect($exception)->toBeInstanceOf(VoucherStackingException::class)
            ->and($exception->getMessage())->toBe('Maximum of 3 voucher(s) allowed per cart.');
    });

    it('creates stacking not allowed exception', function (): void {
        $exception = VoucherStackingException::stackingNotAllowed();

        expect($exception)->toBeInstanceOf(VoucherStackingException::class)
            ->and($exception->getMessage())->toBe('Voucher stacking is not allowed.');
    });

    it('creates mutually exclusive exception', function (): void {
        $exception = VoucherStackingException::mutuallyExclusive('NEW20', 'EXISTING10');

        expect($exception)->toBeInstanceOf(VoucherStackingException::class)
            ->and($exception->getMessage())->toBe("Voucher 'NEW20' cannot be combined with 'EXISTING10'.");
    });
});

describe('VoucherValidationException', function (): void {
    it('creates invalid exception for single voucher', function (): void {
        $exception = VoucherValidationException::invalid('TEST10', 'Voucher has expired');

        expect($exception)->toBeInstanceOf(VoucherValidationException::class)
            ->and($exception->getMessage())->toBe("Voucher 'TEST10' is no longer valid: Voucher has expired")
            ->and($exception->getInvalidVouchers())->toBe(['TEST10' => 'Voucher has expired']);
    });

    it('creates exception for multiple invalid vouchers', function (): void {
        $invalidVouchers = [
            'CODE1' => 'Expired',
            'CODE2' => 'Usage limit reached',
            'CODE3' => 'Below minimum order',
        ];

        $exception = VoucherValidationException::multipleInvalid($invalidVouchers);

        expect($exception)->toBeInstanceOf(VoucherValidationException::class)
            ->and($exception->getMessage())->toContain('3 voucher(s) are no longer valid')
            ->and($exception->getMessage())->toContain('CODE1')
            ->and($exception->getMessage())->toContain('CODE2')
            ->and($exception->getMessage())->toContain('CODE3')
            ->and($exception->getInvalidVouchers())->toBe($invalidVouchers);
    });

    it('returns empty array for default exception', function (): void {
        $exception = new VoucherValidationException('Generic validation error');

        expect($exception->getInvalidVouchers())->toBe([]);
    });
});
