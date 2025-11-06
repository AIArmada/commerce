<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\FpxType;

describe('FpxType Enum', function (): void {
    it('has B2C and B2B1 types', function (): void {
        $types = FpxType::cases();

        expect($types)->toHaveCount(2);
        expect(FpxType::B2C->value)->toBe('fpx');
        expect(FpxType::B2B1->value)->toBe('fpx_b2b1');
    });

    it('returns correct labels for FPX types', function (): void {
        expect(FpxType::B2C->label())->toBe('FPX B2C (Standard FPX)');
        expect(FpxType::B2B1->label())->toBe('FPX B2B1 (Business/Corporate Account)');
    });

    it('can be used in direct post URLs', function (): void {
        $b2cValue = FpxType::B2C->value;
        $b2b1Value = FpxType::B2B1->value;

        $b2cUrl = "?preferred={$b2cValue}&fpx_bank_code=MB2U0227";
        $b2b1Url = "?preferred={$b2b1Value}&fpx_bank_code=MB2U0227";

        expect($b2cUrl)->toBe('?preferred=fpx&fpx_bank_code=MB2U0227');
        expect($b2b1Url)->toBe('?preferred=fpx_b2b1&fpx_bank_code=MB2U0227');
    });
});
