<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\FilamentAffiliates\Services\PayoutExportService;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function (): void {
    AffiliatePayout::query()->delete();
    Affiliate::query()->delete();

    config(['commerce-support.currency.exchange_rates' => [
        'base' => 'USD',
        'rates' => ['MYR' => 4.0],
        'history' => [],
    ]]);
});

function settlementExportAffiliate(): Affiliate
{
    return Affiliate::create([
        'code' => 'SETEXP-' . uniqid(),
        'name' => 'Settlement Export Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
}

it('downloads a settlement csv with per-currency legs and a labeled converted total', function (): void {
    $affiliate = settlementExportAffiliate();

    foreach ([['USD', 10000, 'SETEXP-USD'], ['MYR', 40000, 'SETEXP-MYR']] as [$currency, $total, $ref]) {
        AffiliatePayout::create([
            'reference' => $ref,
            'payee_type' => $affiliate->getMorphClass(),
            'payee_id' => $affiliate->id,
            'total_minor' => $total,
            'currency' => $currency,
            'status' => PendingPayout::class,
            'created_at' => CarbonImmutable::parse('2026-06-15 12:00:00'),
        ]);
    }

    $response = app(PayoutExportService::class)->downloadSettlementCsv('2026-01-01', '2026-12-31');

    expect($response)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    // Per-currency legs.
    expect($content)->toContain('USD')
        ->and($content)->toContain('MYR')
        ->and($content)->toContain('SETEXP-USD')
        ->and($content)->toContain('SETEXP-MYR')
        // Labeled converted total: 10,000 USD @ 4.0 = 40,000 MYR + 40,000 MYR leg.
        ->and($content)->toContain('800.00')
        ->and($content)->toContain('2026-12-31');
});
