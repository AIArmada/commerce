<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Services\Tax\Tax1099Generator;
use AIArmada\Affiliates\Services\Tax\TaxDocumentService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\CompletedPayout;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

function taxCurrencyAffiliate(string $code): Affiliate
{
    return Affiliate::create([
        'code' => $code,
        'name' => 'Tax Currency Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
        'tax_info' => ['tin' => '123-45-6789', 'legal_name' => 'Tax Tester'],
    ]);
}

function taxCurrencyPayout(Affiliate $affiliate, string $currency, int $total, string $ref): AffiliatePayout
{
    return AffiliatePayout::create([
        'reference' => $ref,
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->id,
        'total_minor' => $total,
        'currency' => $currency,
        'status' => CompletedPayout::class,
        'paid_at' => Carbon::create(2024, 6, 15),
    ]);
}

test('1099 threshold and totals use the configured threshold currency', function (): void {
    Storage::fake('local');
    config(['affiliates.tax.1099_threshold' => 60000]);
    config(['affiliates.tax.1099_threshold_currency' => 'MYR']);

    $affiliate = taxCurrencyAffiliate('TAXCUR001');
    taxCurrencyPayout($affiliate, 'MYR', 70000, 'TAXCUR-MYR');

    $service = app(TaxDocumentService::class);

    expect($service->calculateAnnualPayouts($affiliate, 2024))->toBe(70000);

    $document = $service->generate1099ForAffiliate($affiliate, 2024);

    expect($document?->currency)->toBe('MYR')
        ->and($document?->total_amount_minor)->toBe(70000);
});

test('generated documents disclose payouts excluded by currency', function (): void {
    Storage::fake('local');
    config(['affiliates.tax.1099_threshold' => 60000]);
    config(['affiliates.tax.1099_threshold_currency' => 'USD']);

    $affiliate = taxCurrencyAffiliate('TAXCUR002');
    taxCurrencyPayout($affiliate, 'USD', 70000, 'TAXCUR-USD');
    taxCurrencyPayout($affiliate, 'MYR', 470000, 'TAXCUR-MYR-EXCLUDED');

    $service = app(TaxDocumentService::class);
    $document = $service->generate1099ForAffiliate($affiliate, 2024);

    expect($document?->total_amount_minor)->toBe(70000)
        ->and($document?->notes)->toContain('TAXCUR-MYR-EXCLUDED')
        ->and($service->excludedPayoutsForYear($affiliate, 2024)->pluck('reference')->all())->toBe(['TAXCUR-MYR-EXCLUDED']);
});

test('foreign-only earners surface excluded payouts even below threshold', function (): void {
    config(['affiliates.tax.1099_threshold' => 60000]);
    config(['affiliates.tax.1099_threshold_currency' => 'USD']);

    $affiliate = taxCurrencyAffiliate('TAXCUR003');
    taxCurrencyPayout($affiliate, 'EUR', 50000, 'TAXCUR-EUR');

    $service = app(TaxDocumentService::class);

    expect($service->generate1099ForAffiliate($affiliate, 2024))->toBeNull()
        ->and($service->excludedPayoutsForYear($affiliate, 2024)->pluck('reference')->all())->toBe(['TAXCUR-EUR']);
});

test('1099 threshold boundary is inclusive', function (): void {
    Storage::fake('local');
    config(['affiliates.tax.1099_threshold' => 60000]);

    $affiliate = taxCurrencyAffiliate('TAXCUR004');
    taxCurrencyPayout($affiliate, 'USD', 60000, 'TAXCUR-BOUNDARY');

    expect(app(TaxDocumentService::class)->generate1099ForAffiliate($affiliate, 2024))->not->toBeNull();
});

test('generator formats Box 1 in the document currency', function (): void {
    Storage::fake('local');

    $affiliate = taxCurrencyAffiliate('TAXCUR005');

    $path = app(Tax1099Generator::class)->generate([
        'affiliate' => $affiliate,
        'year' => 2024,
        'total_amount' => 70000,
        'currency' => 'MYR',
        'tax_info' => ['legal_name' => 'Tax Tester', 'tin' => '123-45-6789'],
    ]);

    $content = Storage::disk('local')->get($path);

    expect($content)->toContain('RM700.00')
        ->and($content)->not->toContain('$700.00');
});
