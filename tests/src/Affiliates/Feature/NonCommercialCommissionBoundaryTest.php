<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\AttachAffiliateToCart;
use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateConversion;
use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateOutcome;
use AIArmada\Affiliates\Actions\Payouts\CreatePayout;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\States\ApprovedConversion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

function nonCommercialAffiliate(string $code): Affiliate
{
    return createTestAffiliate(['code' => $code, 'purpose' => 'host_internal_only']);
}

function recordedConversionFor(string $externalReference, string $affiliateId): ?AffiliateConversion
{
    return AffiliateConversion::query()
        ->where('external_reference', $externalReference)
        ->where('affiliate_id', $affiliateId)
        ->first();
}

test('explicit commission payload on opaque-purpose affiliate records zero', function (): void {
    $tagged = nonCommercialAffiliate('NCCB-EXPLICIT');
    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($tagged, $cart);
    $cart->add('nccb-item', 'Order item', 10.00, 1);

    $reference = 'NCCB-EXPLICIT-' . Str::uuid();
    app(RecordAffiliateConversion::class)->handle($cart, [
        'affiliate_id' => $tagged->getKey(),
        'external_reference' => $reference,
        'commission' => 2000,
    ]);

    $conversion = recordedConversionFor($reference, (string) $tagged->getKey());

    expect($conversion)->not->toBeNull()
        ->and((int) $conversion->commission_minor)->toBe(0);
});

test('voucher commission override on opaque-purpose affiliate records zero', function (): void {
    $tagged = nonCommercialAffiliate('NCCB-VOUCHER');
    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($tagged, $cart);
    $cart->add('nccb-item', 'Order item', 10.00, 1);

    $reference = 'NCCB-VOUCHER-' . Str::uuid();
    app(RecordAffiliateConversion::class)->handle($cart, [
        'affiliate_id' => $tagged->getKey(),
        'external_reference' => $reference,
        'commission_override' => ['type' => 'fixed', 'value' => 1500],
    ]);

    $conversion = recordedConversionFor($reference, (string) $tagged->getKey());

    expect($conversion)->not->toBeNull()
        ->and((int) $conversion->commission_minor)->toBe(0);
});

test('program rule commission on opaque-purpose affiliate records zero', function (): void {
    $tagged = nonCommercialAffiliate('NCCB-PROGRAM');
    $program = AffiliateProgram::create([
        'name' => 'NCCB Program',
        'slug' => 'nccb-program-' . uniqid(),
        'status' => ProgramStatus::Active,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($tagged, $cart);
    $cart->add('nccb-item', 'Order item', 10.00, 1);

    $reference = 'NCCB-PROGRAM-' . Str::uuid();
    app(RecordAffiliateConversion::class)->handle($cart, [
        'affiliate_id' => $tagged->getKey(),
        'affiliate_program_id' => $program->getKey(),
        'external_reference' => $reference,
    ]);

    $conversion = recordedConversionFor($reference, (string) $tagged->getKey());

    expect($conversion)->not->toBeNull()
        ->and((int) $conversion->commission_minor)->toBe(0);
});

test('calculator commission on opaque-purpose affiliate records zero', function (): void {
    $tagged = nonCommercialAffiliate('NCCB-CALC');
    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($tagged, $cart);
    $cart->add('nccb-item', 'Order item', 10.00, 1);

    $reference = 'NCCB-CALC-' . Str::uuid();
    app(RecordAffiliateConversion::class)->handle($cart, [
        'affiliate_id' => $tagged->getKey(),
        'external_reference' => $reference,
    ]);

    $conversion = recordedConversionFor($reference, (string) $tagged->getKey());

    expect($conversion)->not->toBeNull()
        ->and((int) $conversion->commission_minor)->toBe(0);
});

test('weighted opaque recipient mints nothing while commercial primary still earns', function (): void {
    config(['affiliates.tracking.attribution_model' => 'linear']);

    $commercial = createTestAffiliate(['code' => 'NCCB-MIXED-A']);
    $tagged = nonCommercialAffiliate('NCCB-MIXED-B');

    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($commercial, $cart);
    $cart->add('nccb-item', 'Order item', 10.00, 1);

    $attribution = AffiliateAttribution::query()
        ->where('affiliate_id', $commercial->getKey())
        ->latest('id')
        ->firstOrFail();

    AffiliateTouchpoint::create([
        'affiliate_attribution_id' => $attribution->getKey(),
        'affiliate_id' => $tagged->getKey(),
        'affiliate_code' => $tagged->code,
        'ip_address' => '192.0.2.10',
        'user_agent' => 'NCCB-Test',
        'touched_at' => CarbonImmutable::now(),
    ]);

    $reference = 'NCCB-MIXED-' . Str::uuid();
    app(RecordAffiliateConversion::class)->handle($cart, [
        'affiliate_id' => $commercial->getKey(),
        'external_reference' => $reference,
        'commission' => 2000,
    ]);

    $opaqueTotal = AffiliateConversion::query()
        ->where('external_reference', $reference)
        ->where('affiliate_id', $tagged->getKey())
        ->sum('commission_minor');
    $commercialTotal = AffiliateConversion::query()
        ->where('external_reference', $reference)
        ->where('affiliate_id', $commercial->getKey())
        ->sum('commission_minor');

    expect((int) $opaqueTotal)->toBe(0)
        ->and((int) $commercialTotal)->toBeGreaterThan(0);
});

test('outcome commission override on opaque-purpose affiliate records zero without balance movement', function (): void {
    $tagged = nonCommercialAffiliate('NCCB-OUTCOME');

    $attribution = AffiliateAttribution::create([
        'affiliate_id' => $tagged->getKey(),
        'affiliate_code' => $tagged->code,
        'cookie_value' => 'nccb-outcome-' . uniqid(),
        'cart_instance' => 'default',
        'first_seen_at' => CarbonImmutable::now()->subHour(),
        'last_seen_at' => CarbonImmutable::now()->subHour(),
    ]);

    $result = app(RecordAffiliateOutcome::class)->handle(
        $attribution,
        'host_signal',
        'nccb-test:outcome:' . Str::uuid(),
        [
            'commission_minor' => 5000,
            'subtotal_minor' => 10000,
            'value_minor' => 10000,
            'commission_currency' => 'USD',
            'dispatch_event' => false,
        ],
    );

    expect($result->conversion)->not->toBeNull()
        ->and((int) $result->conversion->commissionMinor)->toBe(0)
        ->and((int) AffiliateBalance::query()->where('affiliate_id', $tagged->getKey())->sum('lifetime_earnings_minor'))->toBe(0);
});

test('reward-award settlement for opaque purposes still settles', function (): void {
    config(['affiliates.payouts.reward_award_settlement.enabled' => true]);

    $recipient = nonCommercialAffiliate('NCCB-AWARD');
    $conversion = AffiliateConversion::create([
        'affiliate_id' => $recipient->getKey(),
        'affiliate_code' => $recipient->code,
        'external_reference' => 'NCCB-AWARD-' . Str::uuid(),
        'conversion_type' => 'reward_award',
        'status' => ApprovedConversion::class,
        'occurred_at' => CarbonImmutable::now(),
        'subtotal_minor' => 0,
        'value_minor' => 0,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
    ]);

    AffiliateBalance::query()->create([
        'affiliate_id' => $recipient->getKey(),
        'currency' => 'USD',
        'holding_minor' => 0,
        'available_minor' => 1000,
        'lifetime_earnings_minor' => 1000,
        'minimum_payout_minor' => 0,
    ]);

    $payout = CreatePayout::run([(string) $conversion->getKey()]);

    expect($payout->conversion_count)->toBe(1)
        ->and($payout->total_minor)->toBe(1000);
});
