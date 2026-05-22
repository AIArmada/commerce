<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\FpxBank;

describe('FpxBank Enum', function (): void {
    it('has all required bank codes', function (): void {
        $banks = FpxBank::cases();

        expect($banks)->toHaveCount(32);
        expect(FpxBank::MAYBANK2U->value)->toBe('MB2U0227');
        expect(FpxBank::CIMB_BANK->value)->toBe('BCBB0235');
        expect(FpxBank::PUBLIC_BANK->value)->toBe('PBB0233');
        expect(FpxBank::AFFINMAX->value)->toBe('ABB0235');
        expect(FpxBank::PUBLIC_BANK_PB_ENTERPRISE->value)->toBe('PBB0234');
        expect(FpxBank::UOB_REGIONAL->value)->toBe('UOB0228');
        expect(FpxBank::MBSB_BANK->value)->toBe('MBSB001');
    });

    it('returns correct labels for banks', function (): void {
        expect(FpxBank::MAYBANK2U->label())->toBe('Maybank2u');
        expect(FpxBank::CIMB_BANK->label())->toBe('CIMB Bank');
        expect(FpxBank::PUBLIC_BANK->label())->toBe('Public Bank');
        expect(FpxBank::AFFIN_BANK->label())->toBe('Affin Bank');
        expect(FpxBank::AFFINMAX->label())->toBe('AFFINMAX');
        expect(FpxBank::PUBLIC_BANK_PB_ENTERPRISE->label())->toBe('Public Bank PB enterprise');
        expect(FpxBank::UOB_REGIONAL->label())->toBe('UOB Regional');
        expect(FpxBank::MBSB_BANK->label())->toBe('MBSB Bank');
    });

    it('converts all banks to array', function (): void {
        $array = FpxBank::toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKey('MB2U0227');
        expect($array['MB2U0227'])->toBe('Maybank2u');
        expect($array['BCBB0235'])->toBe('CIMB Bank');
        expect($array['ABB0235'])->toBe('AFFINMAX');
        expect($array['PBB0234'])->toBe('Public Bank PB enterprise');
        expect($array['UOB0228'])->toBe('UOB Regional');
        expect($array['MBSB001'])->toBe('MBSB Bank');
    });

    it('can find bank by code case-insensitively', function (): void {
        $bank1 = FpxBank::fromCode('MB2U0227');
        $bank2 = FpxBank::fromCode('mb2u0227');
        $bank3 = FpxBank::fromCode('Mb2U0227');

        expect($bank1)->toBe(FpxBank::MAYBANK2U);
        expect($bank2)->toBe(FpxBank::MAYBANK2U);
        expect($bank3)->toBe(FpxBank::MAYBANK2U);
    });

    it('returns null for invalid bank code', function (): void {
        $bank = FpxBank::fromCode('INVALID');

        expect($bank)->toBeNull();
    });

    it('includes all major Malaysian banks', function (): void {
        $bankCodes = array_column(FpxBank::cases(), 'value');

        expect($bankCodes)->toContain('MB2U0227'); // Maybank2u
        expect($bankCodes)->toContain('BCBB0235'); // CIMB
        expect($bankCodes)->toContain('PBB0233'); // Public Bank
        expect($bankCodes)->toContain('HLB0224'); // Hong Leong
        expect($bankCodes)->toContain('RHB0218'); // RHB
        expect($bankCodes)->toContain('BIMB0340'); // Bank Islam
        expect($bankCodes)->toContain('BKRM0602'); // Bank Rakyat
        expect($bankCodes)->toContain('ABB0235'); // AFFINMAX (B2B1)
        expect($bankCodes)->toContain('PBB0234'); // Public Bank PB enterprise (B2B1)
        expect($bankCodes)->toContain('UOB0228'); // UOB Regional (B2B1)
        expect($bankCodes)->toContain('MBSB001'); // MBSB Bank (online docs only)
    });
});
