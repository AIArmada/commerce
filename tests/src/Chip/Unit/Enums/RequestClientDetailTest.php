<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\RequestClientDetail;

describe('RequestClientDetail Enum', function (): void {
    it('holds the twelve spec fields in order', function (): void {
        expect(array_map(fn (RequestClientDetail $case): string => $case->value, RequestClientDetail::cases()))->toBe([
            'email',
            'phone',
            'full_name',
            'personal_code',
            'brand_name',
            'legal_name',
            'registration_number',
            'tax_number',
            'bank_account',
            'bank_code',
            'billing_address',
            'shipping_address',
        ]);
    });

    it('resolves exact values only', function (): void {
        expect(RequestClientDetail::tryFrom('email'))->toBe(RequestClientDetail::EMAIL)
            ->and(RequestClientDetail::tryFrom('Email'))->toBeNull()
            ->and(RequestClientDetail::tryFrom('bogus'))->toBeNull();
    });
});
