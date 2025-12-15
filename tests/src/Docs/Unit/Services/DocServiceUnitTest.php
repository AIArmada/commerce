<?php

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\Services\SequenceManager;
use AIArmada\Docs\Numbering\NumberStrategyRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Browsershot\Browsershot;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('docs.storage.disk', 'docs');
    $this->numberRegistry = new NumberStrategyRegistry();
    $this->service = new DocService($this->numberRegistry);
});

test('generatePdf creates and stores pdf', function () {
    Storage::fake('docs');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-001',
    ]);

    // Mock PDF Facade
    $browsershotMock = Mockery::mock(Browsershot::class);
    $browsershotMock->shouldReceive('showBackground')->andReturnSelf();
    $browsershotMock->shouldReceive('pdf')->andReturn('PDF CONTENT');

    $pdfBuilderMock = Mockery::mock(PdfBuilder::class);
    $pdfBuilderMock->shouldReceive('format')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('orientation')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('margins')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('getBrowsershot')->andReturn($browsershotMock);

    Pdf::shouldReceive('view')
        ->once()
        ->withArgs(function ($view, $data) use ($doc) {
            return $data['doc']->id === $doc->id;
        })
        ->andReturn($pdfBuilderMock);

    $path = $this->service->generatePdf($doc);

    expect($path)->toBe('docs/INV-001.pdf');
    Storage::disk('docs')->assertExists('docs/INV-001.pdf');
    expect($doc->fresh()->pdf_path)->toBe('docs/INV-001.pdf');
});

test('downloadPdf returns existing path if exists', function () {
    Storage::fake('docs');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-001',
        'pdf_path' => 'docs/INV-001.pdf',
    ]);

    Storage::disk('docs')->put('docs/INV-001.pdf', 'dummy content');

    // Should NOT call generatePdf (Pdf facade)
    Pdf::shouldReceive('view')->never();

    $path = $this->service->downloadPdf($doc);
    expect($path)->toBe('docs/INV-001.pdf');
});

test('downloadPdf generates pdf if missing', function () {
    Storage::fake('docs');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-002',
        // pdf_path might be set but file missing
        'pdf_path' => 'docs/INV-002.pdf',
    ]);

    // File content missing in storage

    // Mock PDF Facade
    $browsershotMock = Mockery::mock(Browsershot::class);
    $browsershotMock->shouldReceive('showBackground')->andReturnSelf();
    $browsershotMock->shouldReceive('pdf')->andReturn('PDF CONTENT');

    $pdfBuilderMock = Mockery::mock(PdfBuilder::class);
    $pdfBuilderMock->shouldReceive('format')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('orientation')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('margins')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('getBrowsershot')->andReturn($browsershotMock);

    Pdf::shouldReceive('view')->andReturn($pdfBuilderMock);

    $path = $this->service->downloadPdf($doc);
    expect($path)->toBe('docs/INV-002.pdf');
});

test('emailDoc marks doc as sent', function () {
    $doc = Doc::factory()->create([
        'status' => DocStatus::DRAFT,
    ]);

    $this->service->emailDoc($doc, 'test@example.com');

    expect($doc->fresh()->status)->toBe(DocStatus::SENT);
});
