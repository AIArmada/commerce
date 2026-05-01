<?php

declare(strict_types=1);

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Cancelled;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Sent;
use AIArmada\FilamentDocs\Resources\DocResource\Pages\ViewDoc;
use AIArmada\FilamentDocs\Resources\DocResource\Tables\DocsTable;

it('uses state-aware visibility for mark as sent actions', function (): void {
    $draftDoc = Doc::factory()->create(['status' => Draft::class]);
    $pendingDoc = Doc::factory()->create(['status' => Pending::class]);
    $sentDoc = Doc::factory()->create(['status' => Sent::class]);

    $viewMethod = new ReflectionMethod(ViewDoc::class, 'canMarkAsSent');
    $viewMethod->setAccessible(true);

    $tableMethod = new ReflectionMethod(DocsTable::class, 'canMarkAsSent');
    $tableMethod->setAccessible(true);

    expect($viewMethod->invoke(null, $draftDoc))->toBeTrue();
    expect($viewMethod->invoke(null, $pendingDoc))->toBeTrue();
    expect($viewMethod->invoke(null, $sentDoc))->toBeFalse();

    expect($tableMethod->invoke(null, $draftDoc))->toBeTrue();
    expect($tableMethod->invoke(null, $pendingDoc))->toBeTrue();
    expect($tableMethod->invoke(null, $sentDoc))->toBeFalse();
});

it('uses state-aware visibility for cancel action', function (): void {
    $draftDoc = Doc::factory()->create(['status' => Draft::class]);
    $paidDoc = Doc::factory()->create(['status' => Paid::class]);
    $cancelledDoc = Doc::factory()->create(['status' => Cancelled::class]);

    $method = new ReflectionMethod(ViewDoc::class, 'canCancel');
    $method->setAccessible(true);

    expect($method->invoke(null, $draftDoc))->toBeTrue();
    expect($method->invoke(null, $paidDoc))->toBeFalse();
    expect($method->invoke(null, $cancelledDoc))->toBeFalse();
});
