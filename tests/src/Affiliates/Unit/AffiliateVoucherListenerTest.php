<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Listeners\AttachAffiliateFromVoucher;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\States\Active as AffiliateActive;
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

    $attribution = AffiliateAttribution::firstOrFail();

    expect(AffiliateAttribution::count())->toBe(1)
        ->and($attribution->voucher_code)->toBe('PROMO-1');
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

test('listener preserves affiliate commission and program overrides from vouchers', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Voucher Program',
        'status' => ProgramStatus::Active,
        'requires_approval' => false,
        'visibility' => ProgramVisibility::Public,
        'default_commission_rate_basis_points' => 1000,
        'commission_type' => CommissionType::Percentage,
        'cookie_lifetime_days' => 30,
    ]);

    $voucher = VoucherData::fromArray([
        'id' => (string) Str::uuid(),
        'code' => 'PROMO-OVERRIDE',
        'name' => 'Promo Override',
        'type' => VoucherType::Fixed->value,
        'value' => 1000,
        'currency' => 'USD',
        'status' => Active::class,
        'metadata' => [
            'affiliate_code' => 'VOUCHER-AFF',
        ],
        'affiliate_commission_type' => CommissionType::Fixed,
        'affiliate_commission_value' => 2500,
        'affiliate_program_id' => $program->id,
        'affiliate_upline_levels' => [
            ['level' => 1, 'share' => 0.05],
            ['level' => 2, 'share' => 0.025],
        ],
    ]);

    $cart = app('cart')->getCurrentCart();

    app(AttachAffiliateFromVoucher::class)->handle(new VoucherApplied($cart, $voucher));

    $attribution = AffiliateAttribution::firstOrFail();

    expect($attribution->commission_override)->toBe([
        'type' => CommissionType::Fixed->value,
        'value' => 2500,
    ])
        ->and($attribution->affiliate_program_id)->toBe($program->id)
        ->and($attribution->upline_levels)->toBe([
            ['level' => 1, 'share' => 0.05],
            ['level' => 2, 'share' => 0.025],
        ]);
});
