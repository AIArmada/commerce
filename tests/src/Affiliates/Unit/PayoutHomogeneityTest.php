<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Conversions\Api\PayoutConversionIntegrity;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutReconciliation;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\Features\Payouts\States\ProcessingPayout;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;

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

    expect(fn (): bool => app(PayoutReconciliation::class)->reconcile((string) $payout->getKey(), 'completed'))
        ->toThrow(InvalidArgumentException::class, 'same currency');
});

test('payout integrity sees conversions hidden by the current owner scope', function (): void {
    config(['affiliates.owner.enabled' => true, 'affiliates.owner.include_global' => false]);

    $ownerA = User::query()->create([
        'name' => 'Payout Owner A', 'email' => 'payout-integrity-a@example.com', 'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Payout Owner B', 'email' => 'payout-integrity-b@example.com', 'password' => 'secret',
    ]);

    $payout = OwnerContext::withOwner($ownerA, function (): AffiliatePayout {
        $affiliate = homogeneityAffiliate();

        return homogeneityPayout($affiliate, 'MYR');
    });

    $conversion = OwnerContext::withOwner($ownerB, fn (): AffiliateConversion => homogeneityConversion(homogeneityAffiliate(), 'USD'));
    AffiliateConversion::query()->withoutOwnerScope()->whereKey($conversion->id)->update(['affiliate_payout_id' => $payout->id]);

    OwnerContext::withOwner($ownerA, function () use ($payout): void {
        expect(fn () => $payout->assertHomogeneousConversions())->toThrow(InvalidArgumentException::class, 'same currency');

        expect(fn () => $payout->assertSingleAffiliateConversions())->toThrow(InvalidArgumentException::class, 'one affiliate');

        $integrity = app(PayoutConversionIntegrity::class);
        expect($integrity->hasConversions($payout->id, spl_object_id($payout->getConnection())))->toBeTrue();
    });
});
