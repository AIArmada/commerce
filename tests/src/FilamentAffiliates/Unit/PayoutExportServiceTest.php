<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\FilamentAffiliates\Features\Payouts\Queries\PayoutExportService;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function (): void {
    AffiliatePayout::query()->delete();
    AffiliateConversion::query()->delete();
    Affiliate::query()->delete();
});

function createPayoutWithConversions(): AffiliatePayout
{
    $affiliate = Affiliate::create([
        'code' => 'EXPORT-' . Str::uuid(),
        'name' => 'Export Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'reference' => 'PAY-' . Str::uuid(),
        'total_minor' => 15000,
        'currency' => 'USD',
        'status' => PendingPayout::class,
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    // Create conversions linked to this payout
    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'affiliate_payout_id' => $payout->getKey(),
        'external_reference' => 'REF-001',
        'value_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'affiliate_payout_id' => $payout->getKey(),
        'external_reference' => 'ORD-002',
        'value_minor' => 20000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => PaidConversion::class,
        'occurred_at' => now(),
    ]);

    // Reload with conversions
    return AffiliatePayout::with('conversions')->find($payout->getKey());
}

it('downloads payout as CSV', function (): void {
    $payout = createPayoutWithConversions();
    $service = new PayoutExportService;

    $response = $service->downloadCsv($payout);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toBe('text/csv');

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    expect($content)->toContain('Affiliate Code')
        ->and($content)->toContain('Reference')
        ->and($content)->not->toContain('Order Reference')
        ->and($content)->toContain('REF-001')
        ->and($content)->toContain('ORD-002')
        ->and($content)->toContain('approved')
        ->and($content)->toContain('paid');
});

it('downloads a valid XLSX workbook with payout conversion rows', function (): void {
    $payout = createPayoutWithConversions();
    $service = new PayoutExportService;

    $response = $service->downloadExcel($payout);

    expect($response)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    expect($content)->toStartWith('PK')
        ->and($response->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = tempnam(sys_get_temp_dir(), 'affiliate-payout-xlsx-');
    expect($path)->not->toBeFalse();
    file_put_contents($path, $content);
    $reader = new Reader;
    $rows = [];

    try {
        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
    } finally {
        $reader->close();
        unlink($path);
    }

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toContain('Affiliate Code')
        ->and(array_merge(...array_slice($rows, 1)))->toContain('REF-001', 'ORD-002');
});

it('builds PDF HTML for a payout (without invoking external PDF generators)', function (): void {
    $payout = createPayoutWithConversions();
    $service = new PayoutExportService;

    $reflection = new ReflectionClass($service);

    $buildExportData = $reflection->getMethod('buildExportData');
    /** @var array<int, array<string>> $data */
    $data = $buildExportData->invoke($service, $payout);

    $buildPdfHtml = $reflection->getMethod('buildPdfHtml');
    /** @var string $html */
    $html = $buildPdfHtml->invoke($service, $payout, $data);

    expect($html)->toContain('Affiliate Payout Report')
        ->and($html)->toContain('Reference')
        ->and($html)->toContain('REF-001');
});

it('formats payout status safely for both enum and string statuses', function (): void {
    $payout = createPayoutWithConversions();
    $service = new PayoutExportService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getStatusValue');

    /** @var string $stateStatus */
    $stateStatus = $method->invoke($service, $payout);
    expect($stateStatus)->toBe('pending');

    $payout->status = 'processing';

    /** @var string $stringStatus */
    $stringStatus = $method->invoke($service, $payout);
    expect($stringStatus)->toBe('processing');
});

it('streams bytes returned by the Spatie PDF renderer', function (): void {
    $payout = createPayoutWithConversions();
    $bytes = "%PDF-1.7\n% renderer test payload\n%%EOF";
    $builder = Mockery::mock();
    $builder->shouldReceive('format')->once()->with('a4')->andReturnSelf();
    $builder->shouldReceive('base64')->once()->andReturn(base64_encode($bytes));
    Pdf::shouldReceive('html')->once()->withArgs(static fn (string $html): bool => str_contains($html, 'Affiliate Payout Report') && str_contains($html, 'REF-001'))->andReturn($builder);

    $response = (new PayoutExportService)->downloadPdf($payout);
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');

    ob_start();
    $response->sendContent();
    expect((string) ob_get_clean())->toBe($bytes);
});

it('refuses non-PDF renderer output rather than serving HTML as a PDF', function (): void {
    $payout = createPayoutWithConversions();
    $builder = Mockery::mock();
    $builder->shouldReceive('format')->once()->with('a4')->andReturnSelf();
    $builder->shouldReceive('base64')->once()->andReturn(base64_encode('<html>invalid PDF</html>'));
    Pdf::shouldReceive('html')->once()->andReturn($builder);

    expect(fn () => (new PayoutExportService)->downloadPdf($payout))
        ->toThrow(RuntimeException::class, 'Payout PDF renderer returned invalid PDF data.');
});

it('handles payouts with zero conversions', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'EMPTY-' . Str::uuid(),
        'name' => 'Empty Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $payout = AffiliatePayout::create([
        'reference' => 'PAY-EMPTY-' . Str::uuid(),
        'total_minor' => 0,
        'currency' => 'USD',
        'status' => PendingPayout::class,
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    $payout = AffiliatePayout::with('conversions')->find($payout->getKey());

    $service = new PayoutExportService;

    $response = $service->downloadCsv($payout);

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});
