<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PendingConversion;

function createMultiCurrencyAffiliate(): Affiliate
{
    return Affiliate::create([
        'code' => 'MC-' . uniqid(),
        'name' => 'Multi Currency Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
}

function recordConversionInCurrency(Affiliate $affiliate, string $currency, int $commissionMinor, string $status = PendingConversion::class): AffiliateConversion
{
    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'MC-' . $currency . '-' . uniqid(),
        'value_minor' => $commissionMinor * 10,
        'commission_minor' => $commissionMinor,
        'commission_currency' => $currency,
        'status' => $status,
        'occurred_at' => now(),
    ]);

    ApplyConversionAccounting::run($conversion);

    return $conversion;
}

describe('Multi-currency accounting', function (): void {
    test('routes each conversion to its own currency balance', function (): void {
        $affiliate = createMultiCurrencyAffiliate();

        recordConversionInCurrency($affiliate, 'USD', 1000);
        recordConversionInCurrency($affiliate, 'MYR', 2000);

        $balances = AffiliateBalance::query()->where('affiliate_id', $affiliate->id)->get();

        expect($balances)->toHaveCount(2)
            ->and($balances->firstWhere('currency', 'USD')->holding_minor)->toBe(1000)
            ->and($balances->firstWhere('currency', 'MYR')->holding_minor)->toBe(2000);
    });

    test('shares one balance for same-currency conversions', function (): void {
        $affiliate = createMultiCurrencyAffiliate();

        recordConversionInCurrency($affiliate, 'USD', 1000);
        recordConversionInCurrency($affiliate, 'USD', 2500);

        $balances = AffiliateBalance::query()->where('affiliate_id', $affiliate->id)->get();

        expect($balances)->toHaveCount(1)
            ->and($balances->first()->holding_minor)->toBe(3500);
    });

    test('credits approved conversions to available on the matching balance', function (): void {
        $affiliate = createMultiCurrencyAffiliate();

        recordConversionInCurrency($affiliate, 'EUR', 3000, ApprovedConversion::class);

        $balance = $affiliate->balanceFor('EUR');

        expect($balance)->toBeInstanceOf(AffiliateBalance::class)
            ->and($balance->available_minor)->toBe(3000)
            ->and($affiliate->balanceFor('USD'))->toBeNull();
    });

    test('exposes all balances through the balances relation', function (): void {
        $affiliate = createMultiCurrencyAffiliate();

        recordConversionInCurrency($affiliate, 'USD', 1000);
        recordConversionInCurrency($affiliate, 'MYR', 2000);

        expect($affiliate->balances()->pluck('currency')->sort()->values()->all())->toBe(['MYR', 'USD']);
    });
});
