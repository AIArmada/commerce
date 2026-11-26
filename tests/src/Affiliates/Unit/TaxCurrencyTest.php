<?php

declare(strict_types=1);

use AIArmada\Affiliates\Contracts\TaxRecipientProvider;
use AIArmada\Affiliates\Data\TaxRecipientDetails;
use AIArmada\Affiliates\Data\TaxWorksheetRequest;
use AIArmada\Affiliates\Drivers\Tax\SpatieTaxWorksheetWriter;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\States\CompletedPayout;
use AIArmada\Affiliates\Features\Tax\Api\TaxDocumentApi;
use AIArmada\Affiliates\Features\Tax\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Drivers\PdfDriver;
use Spatie\LaravelPdf\PdfOptions;

beforeEach(function (): void {
    $driver = Mockery::mock(PdfDriver::class);
    $driver->shouldReceive('generatePdf')->andReturnUsing(static fn (string $html, ?string $header, ?string $footer, PdfOptions $options): string => "%PDF-1.7\n" . $html);
    app()->instance('laravel-pdf.driver.browsershot', $driver);
});

function taxCurrencyAffiliate(string $code): Affiliate
{
    app()->instance(TaxRecipientProvider::class, new class implements TaxRecipientProvider
    {
        public function find(string $affiliateId): TaxRecipientDetails
        {
            return new TaxRecipientDetails('Tax Tester', '123-45-6789');
        }
    });

    return Affiliate::create([
        'code' => $code,
        'name' => 'Tax Currency Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
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

    $service = app(TaxDocumentApi::class);

    expect($service->calculateAnnualPayouts((string) $affiliate->getKey(), 2024))->toBe(70000);

    $document = $service->generateForAffiliate((string) $affiliate->getKey(), 2024);

    expect($document?->currency)->toBe('MYR')
        ->and($document?->amountMinor)->toBe(70000);
});

test('generated documents disclose payouts excluded by currency', function (): void {
    Storage::fake('local');
    config(['affiliates.tax.1099_threshold' => 60000]);
    config(['affiliates.tax.1099_threshold_currency' => 'USD']);

    $affiliate = taxCurrencyAffiliate('TAXCUR002');
    taxCurrencyPayout($affiliate, 'USD', 70000, 'TAXCUR-USD');
    taxCurrencyPayout($affiliate, 'MYR', 470000, 'TAXCUR-MYR-EXCLUDED');

    $service = app(TaxDocumentApi::class);
    $document = $service->generateForAffiliate((string) $affiliate->getKey(), 2024);

    expect($document?->amountMinor)->toBe(70000)
        ->and($document?->notes)->toContain('TAXCUR-MYR-EXCLUDED')
        ->and(array_column($service->excludedPayoutsForYear((string) $affiliate->getKey(), 2024), 'reference'))->toBe(['TAXCUR-MYR-EXCLUDED']);
});

test('foreign-only earners surface excluded payouts even below threshold', function (): void {
    config(['affiliates.tax.1099_threshold' => 60000]);
    config(['affiliates.tax.1099_threshold_currency' => 'USD']);

    $affiliate = taxCurrencyAffiliate('TAXCUR003');
    taxCurrencyPayout($affiliate, 'EUR', 50000, 'TAXCUR-EUR');

    $service = app(TaxDocumentApi::class);

    expect($service->generateForAffiliate((string) $affiliate->getKey(), 2024))->toBeNull()
        ->and(array_column($service->excludedPayoutsForYear((string) $affiliate->getKey(), 2024), 'reference'))->toBe(['TAXCUR-EUR']);
});

test('1099 threshold boundary is inclusive', function (): void {
    Storage::fake('local');
    config(['affiliates.tax.1099_threshold' => 60000]);

    $affiliate = taxCurrencyAffiliate('TAXCUR004');
    taxCurrencyPayout($affiliate, 'USD', 60000, 'TAXCUR-BOUNDARY');

    expect(app(TaxDocumentApi::class)->generateForAffiliate((string) $affiliate->getKey(), 2024))->not->toBeNull();
});

test('generator formats Box 1 in the document currency', function (): void {
    Storage::fake('local');

    $affiliate = taxCurrencyAffiliate('TAXCUR005');

    $path = app(SpatieTaxWorksheetWriter::class)->write(new TaxWorksheetRequest(
        (string) $affiliate->getKey(),
        2024,
        70000,
        'MYR',
        new TaxRecipientDetails('Tax Tester', '123-45-6789'),
    ));

    $content = Storage::disk('local')->get($path);

    expect($content)->toContain('RM700.00')
        ->and($content)->not->toContain('$700.00');
});

test('annual generation keeps one record per affiliate year and currency', function (): void {
    Storage::fake('local');
    config(['affiliates.tax.1099_threshold' => 60000, 'affiliates.tax.1099_threshold_currency' => 'USD']);
    $affiliate = taxCurrencyAffiliate('TAXCUR-REPEAT');
    taxCurrencyPayout($affiliate, 'USD', 70000, 'TAXCUR-REPEAT-PAYOUT');

    $api = app(TaxDocumentApi::class);
    $first = $api->generateForAffiliate((string) $affiliate->getKey(), 2024);
    $second = $api->generateForAffiliate((string) $affiliate->getKey(), 2024);

    expect($first?->id)->not->toBeNull()
        ->and($second?->id)->toBe($first?->id)
        ->and($api->documentsFor((string) $affiliate->getKey()))->toHaveCount(1);
});

test('annual payout thresholds normalize currency without summing foreign currencies', function (): void {
    config(['affiliates.tax.1099_threshold' => 60000, 'affiliates.tax.1099_threshold_currency' => 'usd']);
    $affiliate = taxCurrencyAffiliate('TAXCUR-NORMALIZED');
    taxCurrencyPayout($affiliate, 'USD', 30000, 'TAXCUR-UPPER');
    $lower = taxCurrencyPayout($affiliate, 'USD', 30000, 'TAXCUR-LOWER');
    AffiliatePayout::query()->whereKey($lower->id)->update(['currency' => 'usd']);
    taxCurrencyPayout($affiliate, 'MYR', 100000, 'TAXCUR-FOREIGN');

    $api = app(TaxDocumentApi::class);

    expect($api->calculateAnnualPayouts($affiliate->id, 2024))->toBe(60000)
        ->and($api->affiliatesRequiring1099(2024))->toContain($affiliate->id)
        ->and(array_column($api->excludedPayoutsForYear($affiliate->id, 2024), 'reference'))->toBe(['TAXCUR-FOREIGN'])
        ->and($api->calculateAnnualPayouts('not-a-uuid', 2024))->toBe(0);
});

test('outer transaction rollback preserves the original tax worksheet and removes the replacement', function (): void {
    Storage::fake('local');
    config(['affiliates.tax.1099_threshold' => 60000, 'affiliates.tax.1099_threshold_currency' => 'USD']);

    $affiliate = taxCurrencyAffiliate('TAXCUR-ROLLBACK');
    taxCurrencyPayout($affiliate, 'USD', 70000, 'TAXCUR-ROLLBACK-PAYOUT');
    $api = app(TaxDocumentApi::class);
    $first = $api->generateForAffiliate($affiliate->id, 2024);
    $original = AffiliateTaxDocument::query()->findOrFail($first->id)->document_path;

    expect(function () use ($affiliate, $api, $original, $first): void {
        $affiliate->getConnection()->transaction(function () use ($api, $original, $first): void {
            $api->regenerate($first->id);
            $replacement = AffiliateTaxDocument::query()->findOrFail($first->id)->document_path;

            expect($replacement)->not->toBe($original);
            Storage::disk('local')->assertExists($original);
            Storage::disk('local')->assertExists($replacement);

            throw new RuntimeException('force tax rollback');
        });
    })->toThrow(RuntimeException::class, 'force tax rollback');

    expect(AffiliateTaxDocument::query()->findOrFail($first->id)->document_path)->toBe($original)
        ->and(Storage::disk('local')->allFiles())->toBe([$original]);
});
