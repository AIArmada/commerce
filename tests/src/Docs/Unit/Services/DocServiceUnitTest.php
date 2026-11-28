<?php

declare(strict_types=1);

use AIArmada\Docs\Contracts\PdfRenderer;
use AIArmada\Docs\Features\Numbering\Actions\SequenceManager;
use AIArmada\Docs\Features\Payments\Actions\RecordDocPayment;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Numbering\DocumentNumberRegistry;
use AIArmada\Docs\Services\DocRenderService;
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\Services\DocTotals;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Sent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('docs.storage.disk', 'docs');
    $this->numberRegistry = new DocumentNumberRegistry;
    $this->sequenceManager = new SequenceManager;
    $this->service = new DocService(
        $this->numberRegistry,
        $this->sequenceManager,
        new DocTotals,
        new RecordDocPayment,
    );
});

test('generatePdf creates and stores pdf', function (): void {
    Storage::fake('docs');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-001',
    ]);

    // Mock PDF Facade
    $pdfBuilderMock = Mockery::mock(PdfBuilder::class);
    $pdfBuilderMock->shouldReceive('format')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('orientation')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('margins')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('withBrowsershot')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('generatePdfContent')->andReturn('PDF CONTENT');

    Pdf::shouldReceive('html')
        ->once()
        ->withArgs(function (string $html) use ($doc) {
            return str_contains($html, $doc->doc_number);
        })
        ->andReturn($pdfBuilderMock);

    $path = $this->service->generatePdf($doc);

    expect($path)->toBe('docs/' . $doc->id . '.pdf');
    Storage::disk('docs')->assertExists('docs/' . $doc->id . '.pdf');
    expect($doc->fresh()->pdf_path)->toBe('docs/' . $doc->id . '.pdf');
});

test('generatePdf sanitizes doc_number to prevent path traversal', function (): void {
    Storage::fake('docs');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV/../../evil',
    ]);

    // Mock PDF Facade
    $pdfBuilderMock = Mockery::mock(PdfBuilder::class);
    $pdfBuilderMock->shouldReceive('format')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('orientation')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('margins')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('withBrowsershot')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('generatePdfContent')->andReturn('PDF CONTENT');

    Pdf::shouldReceive('html')->andReturn($pdfBuilderMock);

    $path = $this->service->generatePdf($doc);

    expect($path)->toBe('docs/' . $doc->id . '.pdf');
    Storage::disk('docs')->assertExists('docs/' . $doc->id . '.pdf');
    expect($doc->fresh()->pdf_path)->toBe('docs/' . $doc->id . '.pdf');
});

test('downloadPdf returns existing path if exists', function (): void {
    Storage::fake('docs');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-001',
    ]);
    $driver = Mockery::mock(PdfRenderer::class);
    $driver->shouldReceive('render')->once()->andReturn('pdf-bytes');
    app()->instance(PdfRenderer::class, $driver);
    app()->forgetInstance(DocRenderService::class);
    app(DocRenderService::class)->storePdf($doc);

    $path = $this->service->downloadPdf($doc);
    expect($path)->toBe('docs/' . $doc->id . '.pdf');
});

test('downloadPdf generates pdf if missing', function (): void {
    Storage::fake('docs');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-002',
    ]);
    // pdf_path might be set but file missing
    $doc->forceFill(['pdf_path' => 'docs/' . $doc->id . '.pdf'])->save();

    // File content missing in storage

    // Mock PDF Facade
    $pdfBuilderMock = Mockery::mock(PdfBuilder::class);
    $pdfBuilderMock->shouldReceive('format')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('orientation')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('margins')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('withBrowsershot')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('generatePdfContent')->andReturn('PDF CONTENT');

    Pdf::shouldReceive('html')->andReturn($pdfBuilderMock);

    $path = $this->service->downloadPdf($doc);
    expect($path)->toBe('docs/' . $doc->id . '.pdf');
});

test('markAsSent marks doc as sent', function (): void {
    $doc = Doc::factory()->create([
        'status' => Draft::class,
    ]);

    $this->service->markAsSent($doc, 'Sent to test@example.com');

    expect($doc->fresh()->status->equals(Sent::class))->toBeTrue();
});
