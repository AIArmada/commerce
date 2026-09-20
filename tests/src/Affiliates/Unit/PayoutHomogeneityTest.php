<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\PayoutReconciliationService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;

function homogeneityAffiliate(): Affiliate
{
    return Affiliate::create([
        'code' => 'HOMO-' . uniqid(),
        'name' => 'Homogeneity Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);
}

function homogeneityConversion(Affiliate $affiliate, string $currency): AffiliateConversion
{
    return AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'HOMO-' . $currency . '-' . uniqid(),
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => $currency,
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);
}

function homogeneityPayout(Affiliate $affiliate, string $currency): AffiliatePayout
{
    return AffiliatePayout::create([
        'reference' => 'HOMO-PAY-' . uniqid(),
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->id,
        'total_minor' => 1000,
        'conversion_count' => 0,
        'currency' => $currency,
        'status' => PendingPayout::value(),
    ]);
}

test('attaching a mismatched conversion to a payout throws', function (): void {
    $affiliate = homogeneityAffiliate();
    $payout = homogeneityPayout($affiliate, 'MYR');
    $conversion = homogeneityConversion($affiliate, 'USD');

    expect(fn (): mixed => $conversion->forceFill(['affiliate_payout_id' => $payout->id])->save())
        ->toThrow(InvalidArgumentException::class, 'same currency');
});

test('attaching a matching conversion to a payout passes', function (): void {
    $affiliate = homogeneityAffiliate();
    $payout = homogeneityPayout($affiliate, 'MYR');
    $conversion = homogeneityConversion($affiliate, 'MYR');

    $conversion->forceFill(['affiliate_payout_id' => $payout->id])->save();

    expect($conversion->fresh()?->affiliate_payout_id)->toBe($payout->id);
});

test('changing payout currency with attached conversions throws', function (): void {
    $affiliate = homogeneityAffiliate();
    $payout = homogeneityPayout($affiliate, 'MYR');
    $conversion = homogeneityConversion($affiliate, 'MYR');
    $conversion->forceFill(['affiliate_payout_id' => $payout->id])->save();

    expect(fn (): mixed => $payout->forceFill(['currency' => 'USD'])->save())
        ->toThrow(InvalidArgumentException::class, 'same currency');
});

test('reconciliation refuses to complete a mixed-currency payout', function (): void {
    $affiliate = homogeneityAffiliate();
    $payout = homogeneityPayout($affiliate, 'MYR');
    $conversion = homogeneityConversion($affiliate, 'USD');

    // Bypass the model guard the way a raw query would.
    AffiliateConversion::query()->whereKey($conversion->id)->update(['affiliate_payout_id' => $payout->id]);
    $payout->forceFill(['status' => ProcessingPayout::value()])->save();

    expect(fn (): bool => app(PayoutReconciliationService::class)->reconcilePayout($payout, 'completed'))
        ->toThrow(InvalidArgumentException::class, 'same currency');
});
