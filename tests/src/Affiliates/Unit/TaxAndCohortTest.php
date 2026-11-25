<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\TaxDocumentStatus;
use AIArmada\Affiliates\Features\Tax\Api\TaxDocumentApi;
use AIArmada\Affiliates\Features\Tax\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\CompletedPayout;
use Carbon\Carbon;

test('TaxDocumentApi calculateAnnualPayouts returns zero with no payouts', function (): void {
    $service = app(TaxDocumentApi::class);

    $affiliate = Affiliate::create([
        'code' => 'TAXCALC001',
        'name' => 'Tax Calc Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    expect($service->calculateAnnualPayouts((string) $affiliate->getKey(), 2024))->toBe(0);
});

test('TaxDocumentApi getDocumentsForAffiliate returns newest first scoped to the affiliate', function (): void {
    $service = app(TaxDocumentApi::class);

    $affiliate = Affiliate::create([
        'code' => 'TAXDOCS001',
        'name' => 'Tax Docs Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $other = Affiliate::create([
        'code' => 'TAXDOCS002',
        'name' => 'Other Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    foreach ([$affiliate->id => [2023, 2024], $other->id => [2024]] as $affiliateId => $years) {
        foreach ($years as $year) {
            AffiliateTaxDocument::create([
                'affiliate_id' => $affiliateId,
                'document_type' => '1099-nec',
                'tax_year' => $year,
                'status' => TaxDocumentStatus::Generated,
                'total_amount_minor' => 70000,
                'currency' => 'USD',
            ]);
        }
    }

    $documents = $service->documentsFor((string) $affiliate->getKey());

    expect(array_column($documents, 'year'))->toBe([2024, 2023]);
});

test('TaxDocumentApi markDocumentAsSent updates status', function (): void {
    $service = app(TaxDocumentApi::class);

    $affiliate = Affiliate::create([
        'code' => 'TAXSENT001',
        'name' => 'Tax Sent Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $document = AffiliateTaxDocument::create([
        'affiliate_id' => $affiliate->id,
        'document_type' => '1099-NEC',
        'tax_year' => 2024,
        'status' => 'generated',
        'total_amount_minor' => 100000,
        'currency' => 'USD',
        'document_path' => 'tax-documents/2024/sent.txt',
    ]);

    $updated = $service->markSent((string) $document->getKey());

    expect($updated->status)->toBe(TaxDocumentStatus::Sent);
    expect($updated->sentAt)->not->toBeNull();
});

test('TaxDocumentApi generate1099ForAffiliate returns null for below threshold', function (): void {
    $service = app(TaxDocumentApi::class);

    config(['affiliates.tax.1099_threshold' => 60000]);

    $affiliate = Affiliate::create([
        'code' => 'TAXBELOW001',
        'name' => 'Below Threshold Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    // No payouts, so below threshold
    $document = $service->generateForAffiliate((string) $affiliate->getKey(), 2024);

    expect($document)->toBeNull();
});

test('TaxDocumentApi 1099 totals count USD payouts only', function (): void {
    $service = app(TaxDocumentApi::class);

    config(['affiliates.tax.1099_threshold' => 60000]);

    $affiliate = Affiliate::create([
        'code' => 'TAXMC001',
        'name' => 'Multicurrency Tax Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    foreach ([
        ['currency' => 'USD', 'total' => 70000, 'ref' => 'TAXMC-USD'],
        ['currency' => 'MYR', 'total' => 470000, 'ref' => 'TAXMC-MYR'],
    ] as $leg) {
        AffiliatePayout::create([
            'reference' => $leg['ref'],
            'payee_type' => $affiliate->getMorphClass(),
            'payee_id' => $affiliate->id,
            'total_minor' => $leg['total'],
            'currency' => $leg['currency'],
            'status' => CompletedPayout::class,
            'paid_at' => Carbon::create(2024, 6, 15),
        ]);
    }

    expect($service->calculateAnnualPayouts((string) $affiliate->getKey(), 2024))->toBe(70000)
        ->and($service->affiliatesRequiring1099(2024))->toContain($affiliate->id);

    $document = $service->generateForAffiliate((string) $affiliate->getKey(), 2024);

    expect($document->amountMinor)->toBe(70000)
        ->and($document->currency)->toBe('USD');
});
