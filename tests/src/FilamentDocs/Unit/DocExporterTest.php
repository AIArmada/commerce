<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\FilamentDocs\Exports\DocExporter;
use Filament\Actions\Exports\Models\Export;

uses(TestCase::class);

it('exports formatted column values for a doc record', function (): void {
    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'status' => DocStatus::PAID,
        'customer_data' => [
            'name' => 'Acme Inc',
            'email' => 'billing@acme.test',
        ],
        'total' => 123.45,
        'currency' => 'MYR',
    ]);

    DocPayment::create([
        'doc_id' => $doc->id,
        'amount' => 23.45,
        'currency' => $doc->currency,
        'payment_method' => 'cash',
        'reference' => 'PAY-1',
        'transaction_id' => null,
        'paid_at' => now(),
        'notes' => null,
    ]);

    $export = new Export;

    $columnMap = array_fill_keys([
        'doc_number',
        'doc_type',
        'status',
        'customer_name',
        'customer_email',
        'paid_amount',
    ], '');

    $exporter = new DocExporter($export, $columnMap, []);

    $values = $exporter($doc);

    expect($values)->toHaveCount(count($columnMap));
    expect($values[0])->toBe($doc->doc_number);
    expect($values[1])->toBe('Invoice');
    expect($values[2])->toBe(DocStatus::PAID->label());
    expect($values[3])->toBe('Acme Inc');
    expect($values[4])->toBe('billing@acme.test');
    expect((float) $values[5])->toBe(23.45);
});

it('preloads paid_amount via modifyQuery to avoid N+1', function (): void {
    $doc = Doc::factory()->create([
        'currency' => 'MYR',
    ]);

    DocPayment::create([
        'doc_id' => $doc->id,
        'amount' => 23.45,
        'currency' => $doc->currency,
        'payment_method' => 'cash',
        'reference' => 'PAY-1',
        'transaction_id' => null,
        'paid_at' => now(),
        'notes' => null,
    ]);

    $docWithAggregate = DocExporter::modifyQuery(Doc::query())
        ->whereKey($doc->id)
        ->firstOrFail();

    expect((float) $docWithAggregate->paid_amount)->toBe(23.45);
});

it('builds completed notification body for successful and failed rows', function (): void {
    $export = new Export;
    $export->successful_rows = 2;

    expect(DocExporter::getCompletedNotificationBody($export))->toContain('2 rows exported');

    $export->failed_rows = 3;
    $export->total_rows = 5;

    expect(DocExporter::getCompletedNotificationBody($export))->toContain('3 rows failed');
});
