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

});
