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
