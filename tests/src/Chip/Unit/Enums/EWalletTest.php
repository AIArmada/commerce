<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\EWallet;

describe('EWallet Enum', function (): void {
    it('can find wallet by code case-insensitively', function (): void {
        $wallet1 = EWallet::fromCode('GrabPay');
        $wallet2 = EWallet::fromCode('grabpay');
        $wallet3 = EWallet::fromCode('GRABPAY');

        expect($wallet1)->toBe(EWallet::GRABPAY);
        expect($wallet2)->toBe(EWallet::GRABPAY);
        expect($wallet3)->toBe(EWallet::GRABPAY);
    });

    it('returns null for invalid wallet code', function (): void {
        $wallet = EWallet::fromCode('INVALID');

        expect($wallet)->toBeNull();
    });

    it('can find wallet by preferred value', function (): void {
        $wallet = EWallet::fromPreferred('razer_grabpay');

        expect($wallet)->toBe(EWallet::GRABPAY);
    });

    it('returns null for invalid preferred value', function (): void {
        $wallet = EWallet::fromPreferred('invalid_wallet');

        expect($wallet)->toBeNull();
    });

    it('finds wallet by preferred value case-insensitively', function (): void {
        expect(EWallet::fromPreferred('RAZER_GRABPAY'))->toBe(EWallet::GRABPAY)
            ->and(EWallet::fromPreferred('Shopee_Pay'))->toBe(EWallet::SHOPEEPAY);
    });

    it('builds razer URL params with a bank code', function (): void {
        expect(EWallet::GRABPAY->urlParams())->toBe([
            'preferred' => 'razer_grabpay',
            'razer_bank_code' => 'GrabPay',
        ])->and(EWallet::ATOME->urlParams())->toBe([
            'preferred' => 'razer_atome',
            'razer_bank_code' => 'Atome',
        ]);
    });

    it('builds ShopeePay URL params without a bank code', function (): void {
        expect(EWallet::SHOPEEPAY->urlParams())->toBe(['preferred' => 'shopee_pay']);
    });

    it('maps Atome through every accessor', function (): void {
        expect(EWallet::ATOME->preferred())->toBe('razer_atome')
            ->and(EWallet::ATOME->label())->toBe('Atome')
            ->and(EWallet::fromPreferred('razer_atome'))->toBe(EWallet::ATOME)
            ->and(EWallet::fromCode('Atome'))->toBe(EWallet::ATOME);
    });

    it('reports a null code for ShopeePay in the array form', function (): void {
        $wallets = EWallet::toArray();

        expect($wallets['SHOPEEPAY']['code'])->toBeNull()
            ->and($wallets['SHOPEEPAY']['preferred'])->toBe('shopee_pay')
            ->and($wallets['ATOME']['code'])->toBe('Atome');
    });

});
