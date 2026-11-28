<?php

declare(strict_types=1);

use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\Contracts\DocumentLifecycle;
use AIArmada\Docs\Features\Approvals\Models\DocApproval;
use AIArmada\Docs\Features\Lifecycle\Models\DocEInvoiceSubmission;
use AIArmada\Docs\Features\Lifecycle\Actions\DeleteStoredDocumentPdf;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Refunded;
use AIArmada\Docs\States\Sent;
use AIArmada\Docs\Support\DocPdfPath;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

it('marks eligible documents as sent once and skips terminal documents', function (): void {
    $draft = Doc::factory()->create(['status' => Draft::class]);
    $paid = Doc::factory()->create(['status' => Paid::class]);
    $lifecycle = app(DocumentLifecycle::class);
    $ids = [(string) $draft->getKey(), (string) $paid->getKey()];

    expect($lifecycle->markSent($ids))->toBe(['marked' => 1, 'skipped' => 1])
        ->and($draft->refresh()->status->equals(Sent::class))->toBeTrue()
        ->and($paid->refresh()->status->equals(Paid::class))->toBeTrue();

    $count = $draft->statusHistories()->count();

    expect($lifecycle->markSent($ids))->toBe(['marked' => 0, 'skipped' => 2])
        ->and($draft->statusHistories()->count())->toBe($count);
});

it('deletes a document through the lifecycle contract', function (): void {
    $doc = Doc::factory()->create(['status' => Draft::class]);
    $id = (string) $doc->getKey();

    app(DocumentLifecycle::class)->delete([$id]);

    expect(Doc::query()->find($id))->toBeNull();
});

it('rolls back a bulk deletion when a paid document is included', function (): void {
    $draft = Doc::factory()->create(['status' => Draft::class]);
    $paid = Doc::factory()->create(['status' => Paid::class]);

    expect(fn () => app(DocumentLifecycle::class)->delete([(string) $draft->getKey(), (string) $paid->getKey()]))
        ->toThrow(LogicException::class);

    expect(Doc::query()->find($draft->getKey()))->not->toBeNull()
        ->and(Doc::query()->find($paid->getKey()))->not->toBeNull();
});

it('does not remove the stored PDF when document deletion rolls back', function (): void {
    $disk = app(DocServiceInterface::class)->resolveStorageDiskForDocType('invoice');
    Storage::fake($disk);
    $doc = Doc::factory()->invoice()->create(['status' => Draft::class]);
    $path = DocPdfPath::forDocument($doc->doc_type, (string) $doc->getKey());
    $doc->forceFill(['pdf_path' => $path])->save();
    Storage::disk($disk)->put($path, 'document');

    expect(fn () => $doc->getConnection()->transaction(function () use ($doc): void {
        app(DocumentLifecycle::class)->delete([(string) $doc->getKey()]);

        throw new RuntimeException('Rollback');
    }))->toThrow(RuntimeException::class, 'Rollback');

    expect(Doc::query()->find($doc->getKey()))->not->toBeNull();
    Storage::disk($disk)->assertExists($path);
});

it('removes only the canonical PDF belonging to the deleted document', function (): void {
    $disk = DocPdfPath::diskForType('invoice');
    Storage::fake($disk);
    $doc = Doc::factory()->invoice()->create();
    $other = Doc::factory()->invoice()->create();
    $id = (string) $doc->getKey();
    $path = DocPdfPath::forDocument('invoice', $id);
    $otherPath = DocPdfPath::forDocument('invoice', (string) $other->getKey());
    Storage::disk($disk)->put($path, 'document');
    Storage::disk($disk)->put($otherPath, 'another document');

    $action = app(DeleteStoredDocumentPdf::class);
    $action->execute($id, 'invoice', $otherPath);
    Storage::disk($disk)->assertExists($otherPath);

    $action->execute($id, 'invoice', $path);
    Storage::disk($disk)->assertMissing($path);
    Storage::disk($disk)->assertExists($otherPath);
});

it('serializes status changes and records a single history for a repeated transition', function (): void {
    $doc = Doc::factory()->create(['status' => Draft::class]);
    $id = (string) $doc->getKey();
    $lifecycle = app(DocumentLifecycle::class);

    expect($lifecycle->changeStatus($id, Sent::class, 'Sent once'))->toBeTrue()
        ->and($lifecycle->changeStatus($id, Sent::class, 'Duplicate'))->toBeFalse();

    $doc->refresh();

    expect($doc->status->equals(Sent::class))->toBeTrue()
        ->and($doc->sent_at)->not->toBeNull()
        ->and($doc->statusHistories()->count())->toBe(1)
        ->and($doc->statusHistories()->firstOrFail()->notes)->toBe('Sent once');
});

it('does not mark a refunded document as overdue or cancelled', function (): void {
    $doc = Doc::factory()->create(['status' => Refunded::class, 'due_date' => now()->subDays(2)]);
    $id = (string) $doc->getKey();
    $lifecycle = app(DocumentLifecycle::class);

    expect($doc->isOverdue())->toBeFalse()
        ->and($lifecycle->detectOverdue($id))->toBeFalse()
        ->and($lifecycle->cancel($id))->toBeFalse()
        ->and($doc->refresh()->status->equals(Refunded::class))->toBeTrue()
        ->and($doc->statusHistories()->count())->toBe(0);
});

it('rejects direct deletion of documents with a terminal financial status', function (): void {
    foreach ([Paid::class, Refunded::class] as $status) {
        $doc = Doc::factory()->create(['status' => $status, 'paid_at' => null]);

        expect(fn () => $doc->delete())->toThrow(LogicException::class, 'payment or e-invoice evidence')
            ->and(Doc::query()->find($doc->getKey()))->not->toBeNull();
    }
});

it('cascades dependent approvals on a bulk database delete', function (): void {
    $doc = Doc::factory()->create(['status' => Draft::class]);
    $approval = DocApproval::factory()->create(['doc_id' => $doc->getKey()]);

    Doc::query()->whereKey($doc->getKey())->delete();

    expect(DocApproval::query()->find($approval->getKey()))->toBeNull();
});

it('protects e-invoice evidence even when deletion bypasses model events', function (): void {
    $doc = Doc::factory()->create(['status' => Draft::class]);
    $submission = DocEInvoiceSubmission::factory()->create(['doc_id' => $doc->getKey()]);

    expect(fn () => $doc->getConnection()->transaction(fn () => Doc::query()->whereKey($doc->getKey())->delete()))
        ->toThrow(QueryException::class);

    expect(Doc::query()->find($doc->getKey()))->not->toBeNull()
        ->and(DocEInvoiceSubmission::query()->find($submission->getKey()))->not->toBeNull();
});

it('does not remove a PDF when another deleting listener vetoes deletion', function (): void {
    $disk = DocPdfPath::diskForType('invoice');
    Storage::fake($disk);
    $doc = Doc::factory()->invoice()->create(['status' => Draft::class]);
    $path = DocPdfPath::forDocument($doc->doc_type, (string) $doc->getKey());
    $doc->forceFill(['pdf_path' => $path])->save();
    Storage::disk($disk)->put($path, 'document');

    Doc::deleting(static fn (Doc $candidate): ?bool => $candidate->is($doc) ? false : null);

    expect($doc->delete())->toBeFalse()
        ->and(Doc::query()->find($doc->getKey()))->not->toBeNull();

    Storage::disk($disk)->assertExists($path);
});
