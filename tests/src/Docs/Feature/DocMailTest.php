<?php

declare(strict_types=1);

use AIArmada\Docs\Mail\DocMail;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmail;
use AIArmada\Docs\Services\DocService;
use Illuminate\Mail\Attachment;
use Illuminate\Support\Facades\Storage;

afterEach(function (): void {
    Mockery::close();
});

test('it builds PDF attachments from the configured storage disk', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('docs/test.pdf', 'pdf-bytes');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-ATTACH-001',
    ]);

    $docEmail = DocEmail::query()->create([
        'doc_id' => $doc->id,
        'recipient_email' => 'attach@example.test',
        'recipient_name' => 'Attach Test',
        'subject' => 'Invoice',
        'body' => 'Attached.',
        'status' => 'sent',
    ]);

    $docService = Mockery::mock(DocService::class);
    $docService->shouldReceive('generatePdf')
        ->once()
        ->withArgs(fn (Doc $mailDoc, bool $save): bool => $mailDoc->is($doc) && $save === true)
        ->andReturn('docs/test.pdf');
    $docService->shouldReceive('resolveStorageDiskForDocType')
        ->once()
        ->with('invoice')
        ->andReturn('local');

    app()->instance(DocService::class, $docService);

    $mail = new DocMail($docEmail, $doc, attachPdf: true);

    $attachments = $mail->attachments();

    expect($attachments)->toHaveCount(1);
    expect($attachments[0]->isEquivalent(
        Attachment::fromStorageDisk('local', 'docs/test.pdf')
            ->as('Invoice-INV-ATTACH-001.pdf')
            ->withMime('application/pdf')
    ))->toBeTrue();
});
