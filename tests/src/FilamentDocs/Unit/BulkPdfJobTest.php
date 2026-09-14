<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\Jobs\GenerateDocPdfsJob;
use AIArmada\FilamentDocs\Support\DocBulkActions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

function filamentDocs_mockPdfGeneration(): void
{
    $pdfBuilderMock = Mockery::mock(PdfBuilder::class);
    $pdfBuilderMock->shouldReceive('format')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('orientation')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('margins')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('withBrowsershot')->andReturnSelf();
    $pdfBuilderMock->shouldReceive('generatePdfContent')->andReturn('PDF CONTENT');

    Pdf::shouldReceive('html')->andReturn($pdfBuilderMock);
}

it('queues bulk pdf generation in chunks instead of rendering in-request (M4)', function (): void {
    Queue::fake();

    $docs = Doc::factory()->count(30)->create();

    $dispatched = DocBulkActions::dispatchPdfJobs($docs);

    expect($dispatched)->toBe(2);

    Queue::assertPushed(GenerateDocPdfsJob::class, 2);

    Queue::assertPushed(GenerateDocPdfsJob::class, fn (GenerateDocPdfsJob $job): bool => count($job->docIds) === 25);
    Queue::assertPushed(GenerateDocPdfsJob::class, fn (GenerateDocPdfsJob $job): bool => count($job->docIds) === 5);

    $notifications = session()->get('filament.notifications', []);
    expect(collect($notifications)->last()['title'])->toContain('queued');
});

it('refuses oversized bulk pdf selections (M4)', function (): void {
    Queue::fake();

    $docs = Doc::factory()->count(501)->create();

    $dispatched = DocBulkActions::dispatchPdfJobs($docs);

    expect($dispatched)->toBe(0);

    Queue::assertNotPushed(GenerateDocPdfsJob::class);

    $notifications = session()->get('filament.notifications', []);
    expect(collect($notifications)->last()['title'])->toContain('Too many');
});

it('generates pdfs only for docs inside the job owner scope (M4)', function (): void {
    config()->set('docs.storage.disk', 'local');
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);
    Storage::fake('local');
    filamentDocs_mockPdfGeneration();

    $ownerA = User::query()->create([
        'name' => 'Pdf Owner A',
        'email' => 'pdf-owner-a@example.test',
        'password' => bcrypt('password'),
    ]);
    $ownerB = User::query()->create([
        'name' => 'Pdf Owner B',
        'email' => 'pdf-owner-b@example.test',
        'password' => bcrypt('password'),
    ]);

    $docA = OwnerContext::withOwner($ownerA, fn (): Doc => Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-PDFA-1',
        'pdf_path' => null,
    ]));
    $docB = OwnerContext::withOwner($ownerB, fn (): Doc => Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-PDFB-1',
        'pdf_path' => null,
    ]));

    $job = new GenerateDocPdfsJob(
        [(string) $docA->getKey(), (string) $docB->getKey(), 'missing-id'],
        OwnerJobContext::fromOwnerModel($ownerA),
    );

    $job->handle();

    expect($docA->fresh()->pdf_path)->not()->toBeNull();
    expect($docB->fresh()->pdf_path)->toBeNull();
});
