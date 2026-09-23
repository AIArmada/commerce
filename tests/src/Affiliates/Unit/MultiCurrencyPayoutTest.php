<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Actions\Payouts\ClaimScheduledPayout;
use AIArmada\Affiliates\Actions\Payouts\CreatePayout;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;

function createPayoutTestAffiliate(): Affiliate
{
    return Affiliate::create([
        'code' => 'MP-' . uniqid(),
        'name' => 'Multi Currency Payout Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
}

function recordApprovedConversion(Affiliate $affiliate, string $currency, int $commissionMinor): AffiliateConversion
{
    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'MP-' . $currency . '-' . uniqid(),
        'value_minor' => $commissionMinor * 10,
        'commission_minor' => $commissionMinor,
        'commission_currency' => $currency,
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    ApplyConversionAccounting::run($conversion);

    return $conversion;
}

function balanceFor(Affiliate $affiliate, string $currency): AffiliateBalance
{
    return AffiliateBalance::query()
        ->where('affiliate_id', $affiliate->id)
        ->where('currency', $currency)
        ->firstOrFail();
}

describe('Multi-currency payouts', function (): void {
    test('CreatePayout rejects mixed-currency conversions', function (): void {
        $affiliate = createPayoutTestAffiliate();
        $usd = recordApprovedConversion($affiliate, 'USD', 1000);
        $myr = recordApprovedConversion($affiliate, 'MYR', 2000);

        expect(fn (): object => CreatePayout::run([$usd->id, $myr->id]))
            ->toThrow(InvalidArgumentException::class, 'only one currency');
    });

    test('CreatePayout rejects an explicit currency that mismatches the conversions', function (): void {
        $affiliate = createPayoutTestAffiliate();
        $usd = recordApprovedConversion($affiliate, 'USD', 1000);

        expect(fn (): object => CreatePayout::run([$usd->id], ['currency' => 'MYR']))
            ->toThrow(InvalidArgumentException::class, 'must match');
    });

    test('CreatePayout reserves only the matching currency balance', function (): void {
        $affiliate = createPayoutTestAffiliate();
        $usd = recordApprovedConversion($affiliate, 'USD', 1000);
        recordApprovedConversion($affiliate, 'MYR', 2000);

        $payout = CreatePayout::run([$usd->id]);

        expect($payout->currency)->toBe('USD')
            ->and($payout->total_minor)->toBe(1000)
            ->and(balanceFor($affiliate, 'USD')->available_minor)->toBe(0)
            ->and(balanceFor($affiliate, 'MYR')->available_minor)->toBe(2000);
    });

    test('scheduled claims pay each currency balance separately', function (): void {
        $affiliate = createPayoutTestAffiliate();
        recordApprovedConversion($affiliate, 'USD', 6000);
        recordApprovedConversion($affiliate, 'MYR', 8000);

        $usdOperation = ClaimScheduledPayout::run((string) $affiliate->id, 5000, 'USD');
        $myrOperation = ClaimScheduledPayout::run((string) $affiliate->id, 5000, 'MYR');

        expect($usdOperation->currency)->toBe('USD')
            ->and($usdOperation->amount_minor)->toBe(6000)
            ->and($myrOperation->currency)->toBe('MYR')
            ->and($myrOperation->amount_minor)->toBe(8000)
            ->and(balanceFor($affiliate, 'USD')->available_minor)->toBe(0)
            ->and(balanceFor($affiliate, 'MYR')->available_minor)->toBe(0);
    });

    test('scheduled allocation sweeps only its own currency conversions', function (): void {
        $affiliate = createPayoutTestAffiliate();
        recordApprovedConversion($affiliate, 'USD', 6000);
        $myr = recordApprovedConversion($affiliate, 'MYR', 8000);

        $operation = ClaimScheduledPayout::run((string) $affiliate->id, 5000, 'USD');

        expect($operation->amount_minor)->toBe(6000)
            ->and($myr->fresh()->affiliate_payout_id)->toBeNull();
    });

    test('a pending payout blocks only its own currency', function (): void {
        $affiliate = createPayoutTestAffiliate();
        recordApprovedConversion($affiliate, 'USD', 6000);
        recordApprovedConversion($affiliate, 'MYR', 8000);

        ClaimScheduledPayout::run((string) $affiliate->id, 5000, 'USD');

        expect(ClaimScheduledPayout::run((string) $affiliate->id, 5000, 'USD'))->toBeNull()
            ->and(ClaimScheduledPayout::run((string) $affiliate->id, 5000, 'MYR'))->not->toBeNull();
    });
});

test('scheduled claims default to the affiliate currency when omitted', function (): void {
    $affiliate = createPayoutTestAffiliate();
    recordApprovedConversion($affiliate, 'USD', 6000);

    $operation = ClaimScheduledPayout::run((string) $affiliate->id, 5000);

    expect($operation)->not->toBeNull()
        ->and(balanceFor($affiliate, 'USD')->available_minor)->toBe(0);
    expect(ClaimScheduledPayout::make()->isEligibleSnapshot((string) $affiliate->id, 5000))->toBeFalse();
});
