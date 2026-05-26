<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocRenderService;
use AIArmada\FilamentDocs\Http\Controllers\DocDownloadController;
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

    $response = (new DocDownloadController)((string) $doc->getKey(), app(DocRenderService::class));

    expect($response->getStatusCode())->toBe(200)
        ->and($doc->fresh()->pdf_path)->toBe('docs/INV-ON-DEMAND.pdf');
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

    $response = (new DocDownloadController)((string) $doc->getKey(), app(DocRenderService::class));

    expect($response->getStatusCode())->toBe(200);
});

it('downloads a document pdf with a safe filename', function (): void {
    config()->set('docs.storage.disk', 'local');
    Storage::fake('local');

    Storage::disk('local')->put('docs/inv.pdf', 'pdf-bytes');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => "INV/2025\n0001",
        'pdf_path' => 'docs/inv.pdf',
    ]);

    $response = (new DocDownloadController)((string) $doc->getKey(), app(DocRenderService::class));

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $disposition = (string) $response->headers->get('content-disposition');
    expect($disposition)->not->toContain("\n")
        ->and($disposition)->not->toContain("\r");
});
