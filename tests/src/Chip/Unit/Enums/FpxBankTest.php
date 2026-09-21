<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\FpxBank;

describe('FpxBank Enum', function (): void {
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

});
