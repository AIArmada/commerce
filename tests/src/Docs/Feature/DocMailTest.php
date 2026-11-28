<?php

declare(strict_types=1);

use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\Features\Delivery\Mail\DocMail;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmail;
use AIArmada\Docs\Support\DocPdfPath;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Storage;

afterEach(function (): void {
    Mockery::close();
});

test('it builds PDF attachments from the configured storage disk', function (): void {
    Storage::fake('local');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-ATTACH-001',
    ]);

    $path = DocPdfPath::forDocument($doc->doc_type, (string) $doc->getKey());
    Storage::disk('local')->put($path, 'pdf-bytes');

    $docEmail = DocEmail::query()->create([
        'doc_id' => $doc->id,
        'recipient_email' => 'attach@example.test',
        'recipient_name' => 'Attach Test',
        'subject' => 'Invoice',
        'body' => 'Attached.',
        'status' => 'sent',
    ]);

    $docService = Mockery::mock(DocServiceInterface::class);
    app()->instance(DocServiceInterface::class, $docService);
    $docService->shouldReceive('generatePdf')
        ->once()
        ->with((string) $doc->getKey(), true)
        ->andReturn($path);
    $docService->shouldReceive('resolveStorageDiskForDocType')
        ->once()
        ->with('invoice')
        ->andReturn('local');

    $mail = new DocMail($docEmail, $doc, attachPdf: true);

    $attachments = $mail->attachments();

    expect($attachments)->toHaveCount(1);
    expect($attachments[0]->isEquivalent(
        Attachment::fromStorageDisk('local', $path)
            ->as('Invoice-INV-ATTACH-001.pdf')
            ->withMime('application/pdf')
    ))->toBeTrue();
});
