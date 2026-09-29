<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateOutcome;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Pending;
use AIArmada\Affiliates\States\PendingConversion;
use Illuminate\Support\Facades\Event;

function createRedispatchAffiliate(string $code): Affiliate
{
    return Affiliate::create([
        'code' => $code,
        'name' => "Redispatch Test {$code}",
        'status' => Pending::class,
        'registration_approval_mode' => 'admin',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
}

function createRedispatchAttribution(Affiliate $affiliate, string $cookie): AffiliateAttribution
{
    return AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => $cookie,
        'cart_instance' => 'default',
        'first_seen_at' => now()->subHour(),
        'last_seen_at' => now()->subHour(),
    ]);
}

test('outcome retry redispatches the recorded event for the existing conversion', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);

    $affiliate = createRedispatchAffiliate('REDISPATCH001');
    $attribution = createRedispatchAttribution($affiliate, 'cookie-redispatch-001');

    $payload = [
        'commission_minor' => 1000,
        'subtotal_minor' => 10000,
        'value_minor' => 10000,
        'commission_currency' => 'USD',
    ];

    $action = app(RecordAffiliateOutcome::class);
    $action->handle($attribution, 'referral', 'ext-retry-1', $payload);
    $action->handle($attribution, 'referral', 'ext-retry-1', $payload);

    Event::assertDispatched(AffiliateConversionRecorded::class, 2);
    expect(AffiliateConversion::query()->where('external_reference', 'ext-retry-1')->count())->toBe(1);
});

test('unique-violation recovery redispatches the recorded event', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);

    $affiliate = createRedispatchAffiliate('REDISPATCH002');
    $attribution = createRedispatchAttribution($affiliate, 'cookie-redispatch-002');

    // Occupy the idempotency key the action will compute, under a
    // different external reference, so the pre-check misses and the
    // save hits the unique violation instead (the concurrent shape).
    $key = hash('sha256', implode('|', ['', '', (string) $affiliate->getKey(), 'referral', 'ext-race-1']));

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'affiliate_attribution_id' => $attribution->getKey(),
        'external_reference' => 'ext-other',
        'idempotency_key' => $key,
        'conversion_type' => 'referral',
        'subtotal_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
        'occurred_at' => now(),
    ]);

    app(RecordAffiliateOutcome::class)->handle($attribution, 'referral', 'ext-race-1', [
        'commission_minor' => 1000,
        'subtotal_minor' => 10000,
        'value_minor' => 10000,
        'commission_currency' => 'USD',
    ]);

    Event::assertDispatched(AffiliateConversionRecorded::class, 1);
});
