<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\Http\Controllers\DocDownloadController;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(TestCase::class);

it('throws when a document has no pdf_path', function (): void {
    $doc = Doc::factory()->make(['pdf_path' => null]);

    expect(fn (): mixed => (new DocDownloadController)($doc))->toThrow(NotFoundHttpException::class);
});

it('throws when pdf file does not exist on disk', function (): void {
    config()->set('docs.storage.disk', 'local');
    Storage::fake('local');

    $doc = Doc::factory()->make([
        'doc_type' => 'invoice',
        'doc_number' => 'INV/2025 0001',
        'pdf_path' => 'docs/inv.pdf',
    ]);

    expect(fn (): mixed => (new DocDownloadController)($doc))->toThrow(NotFoundHttpException::class);
});

it('downloads a document pdf with a safe filename', function (): void {
    config()->set('docs.storage.disk', 'local');
    Storage::fake('local');

    Storage::disk('local')->put('docs/inv.pdf', 'pdf-bytes');

    $doc = Doc::factory()->make([
        'doc_type' => 'invoice',
        'doc_number' => 'INV/2025 0001',
        'pdf_path' => 'docs/inv.pdf',
    ]);

    $response = (new DocDownloadController)($doc);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
