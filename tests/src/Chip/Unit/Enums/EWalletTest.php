<?php

declare(strict_types=1);

use AIArmada\Chip\Enums\EWallet;

describe('EWallet Enum', function (): void {
    it('has all e-wallet options', function (): void {
        $wallets = EWallet::cases();

        expect($wallets)->toHaveCount(4);
        expect(EWallet::GRABPAY->value)->toBe('GrabPay');
        expect(EWallet::TOUCH_N_GO->value)->toBe('TNG-EWALLET');
        expect(EWallet::SHOPEEPAY->value)->toBe('ShopeePay');
        expect(EWallet::MAYBANK_QR->value)->toBe('MB2U_QRPay-Push');
    });

    it('returns correct preferred values', function (): void {
        expect(EWallet::GRABPAY->preferred())->toBe('razer_grabpay');
        expect(EWallet::TOUCH_N_GO->preferred())->toBe('razer_tng');
        expect(EWallet::SHOPEEPAY->preferred())->toBe('razer_shopeepay');
        expect(EWallet::MAYBANK_QR->preferred())->toBe('razer_maybankqr');
    });

    it('returns correct labels', function (): void {
        expect(EWallet::GRABPAY->label())->toBe('GrabPay');
        expect(EWallet::TOUCH_N_GO->label())->toBe("Touch 'n Go eWallet");
        expect(EWallet::SHOPEEPAY->label())->toBe('ShopeePay');
        expect(EWallet::MAYBANK_QR->label())->toBe('Maybank QR');
    });

    it('builds correct URL parameters', function (): void {
        $params = EWallet::GRABPAY->urlParams();

        expect($params)->toBeArray();
        expect($params)->toHaveKey('preferred');
        expect($params)->toHaveKey('razer_bank_code');
        expect($params['preferred'])->toBe('razer_grabpay');
        expect($params['razer_bank_code'])->toBe('GrabPay');
    });

    it('converts all wallets to array', function (): void {
        $array = EWallet::toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKey('GRABPAY');
        expect($array['GRABPAY'])->toHaveKey('label');
        expect($array['GRABPAY'])->toHaveKey('preferred');
        expect($array['GRABPAY'])->toHaveKey('code');
        expect($array['GRABPAY']['label'])->toBe('GrabPay');
        expect($array['GRABPAY']['preferred'])->toBe('razer_grabpay');
        expect($array['GRABPAY']['code'])->toBe('GrabPay');
    });

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

    it('can be used to construct direct post URLs', function (): void {
        $params = EWallet::GRABPAY->urlParams();
        $queryString = http_build_query($params);

        expect($queryString)->toBe('preferred=razer_grabpay&razer_bank_code=GrabPay');
    });
});
