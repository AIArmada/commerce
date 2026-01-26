<?php

declare(strict_types=1);

use AIArmada\Affiliates\Listeners\AttachAffiliateFromVoucher;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\States\Active as AffiliateActive;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\States\Active;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->affiliate = Affiliate::create([
        'code' => 'VOUCHER-AFF',
        'name' => 'Voucher Partner',
        'status' => AffiliateActive::class,
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'USD',
    ]);
});

function dispatchVoucherApplied(array $metadata): void
{
    $cart = app('cart')->getCurrentCart();

    $voucher = VoucherData::fromArray([
        'id' => (string) Str::uuid(),
        'code' => 'PROMO-1',
        'name' => 'Promo Voucher',
        'type' => VoucherType::Fixed->value,
        'value' => 1000,
        'currency' => 'USD',
        'status' => Active::class,
        'metadata' => $metadata,
    ]);

    app(AttachAffiliateFromVoucher::class)->handle(new VoucherApplied($cart, $voucher));
}

test('affiliate attaches when voucher metadata contains affiliate code', function (): void {
    dispatchVoucherApplied(['affiliate_code' => 'VOUCHER-AFF']);

    expect(AffiliateAttribution::count())->toBe(1)
        ->and(Cart::getAffiliateMetadata('voucher_code'))->toBe('PROMO-1');
});

test('listener ignores vouchers without affiliate metadata', function (): void {
    dispatchVoucherApplied(['campaign' => 'spring']);

    expect(AffiliateAttribution::count())->toBe(0);
});

test('voucher code fallback attaches affiliate when metadata missing', function (): void {
    $fallbackAffiliate = Affiliate::create([
        'code' => 'VOUCHER-FALLBACK',
        'name' => 'Fallback Partner',
        'status' => AffiliateActive::class,
        'commission_type' => 'percentage',
        'commission_rate' => 150,
        'currency' => 'USD',
        'default_voucher_code' => 'PROMO-1',
    ]);

    dispatchVoucherApplied([]);

    $attribution = AffiliateAttribution::first();

    expect($attribution)
        ->not()->toBeNull()
        ->and($attribution->affiliate_id)->toBe($fallbackAffiliate->getKey());
});
