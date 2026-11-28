<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Contracts\PdfRenderer;
use AIArmada\Docs\EntryPoints\Http\Controllers\DocDownloadController;
use AIArmada\Docs\Contracts\DocumentPdfPublisher;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Runtime\DocumentRendering;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

uses(TestCase::class);

it('generates a missing pdf before download', function (): void {
    config()->set('docs.storage.disk', 'local');
    Storage::fake('local');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-ON-DEMAND',
        'pdf_path' => null,
    ]);

    $pdfBuilderMock = Mockery::mock(PdfBuilder::class);
    $pdfBuilderMock->shouldReceive('format')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('orientation')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('margins')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('withBrowsershot')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('generatePdfContent')->andReturn('PDF CONTENT');

    Pdf::shouldReceive('html')->andReturn($pdfBuilderMock);

    $response = (new DocDownloadController)((string) $doc->getKey(), app(DocumentRendering::class), app(DocumentPdfPublisher::class));

    expect($response->getStatusCode())->toBe(200)
        ->and($doc->fresh()->pdf_path)->toBe('docs/' . $doc->id . '.pdf');
});

it('regenerates when pdf file does not exist on disk', function (): void {
    config()->set('docs.storage.disk', 'local');
    Storage::fake('local');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV/2025 0001',
        'pdf_path' => 'docs/inv.pdf',
    ]);

    $pdfBuilderMock = Mockery::mock(PdfBuilder::class);
    $pdfBuilderMock->shouldReceive('format')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('orientation')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('margins')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('withBrowsershot')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('generatePdfContent')->andReturn('PDF CONTENT');

    Pdf::shouldReceive('html')->andReturn($pdfBuilderMock);

    $response = (new DocDownloadController)((string) $doc->getKey(), app(DocumentRendering::class), app(DocumentPdfPublisher::class));

    expect($response->getStatusCode())->toBe(200);
});

it('downloads a document pdf with a safe filename', function (): void {
    config()->set('docs.storage.disk', 'local');
    Storage::fake('local');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => "INV/2025\n0001",
    ]);
    $driver = Mockery::mock(PdfRenderer::class);
    $driver->shouldReceive('render')->once()->andReturn('pdf-bytes');
    app()->instance(PdfRenderer::class, $driver);
    app()->forgetInstance(DocumentRendering::class);
    app(DocumentPdfPublisher::class)->execute((string) $doc->getKey());

    $response = (new DocDownloadController)((string) $doc->getKey(), app(DocumentRendering::class), app(DocumentPdfPublisher::class));

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $disposition = (string) $response->headers->get('content-disposition');
    expect($disposition)->not->toContain("\n")
        ->and($disposition)->not->toContain("\r");
});
