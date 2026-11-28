<?php

declare(strict_types=1);

use AIArmada\Docs\Contracts\DocumentLifecycle;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Sent;

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
    $disk = app(\AIArmada\Docs\Contracts\DocServiceInterface::class)->resolveStorageDiskForDocType('invoice');
    \Illuminate\Support\Facades\Storage::fake($disk);
    $doc = Doc::factory()->invoice()->create(['status' => Draft::class]);
    $doc->forceFill(['pdf_path' => 'rollback-document.pdf'])->save();
    \Illuminate\Support\Facades\Storage::disk($disk)->put('rollback-document.pdf', 'document');

    expect(fn () => $doc->getConnection()->transaction(function () use ($doc): void {
        app(DocumentLifecycle::class)->delete([(string) $doc->getKey()]);
        throw new RuntimeException('Rollback');
    }))->toThrow(RuntimeException::class, 'Rollback');

    expect(Doc::query()->find($doc->getKey()))->not->toBeNull();
    \Illuminate\Support\Facades\Storage::disk($disk)->assertExists('rollback-document.pdf');
});
