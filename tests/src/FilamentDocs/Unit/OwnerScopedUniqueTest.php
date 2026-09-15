<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerUniqueRule;
use AIArmada\Docs\Models\Doc;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Unique;

uses(TestCase::class);

function filamentDocs_scopedDocNumberRule(): Unique
{
    $table = (string) config('docs.database.tables.docs', 'docs');

    return OwnerUniqueRule::scopeToOwner(new Unique($table, 'doc_number'), Doc::class);
}

it('scopes document-number uniqueness to the current owner (H4)', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Number Owner A',
        'email' => 'number-owner-a@example.test',
        'password' => bcrypt('password'),
    ]);
    $ownerB = User::query()->create([
        'name' => 'Number Owner B',
        'email' => 'number-owner-b@example.test',
        'password' => bcrypt('password'),
    ]);

    OwnerContext::withOwner($ownerA, fn (): Doc => Doc::factory()->create(['doc_number' => 'INV-SHARED-1']));

    // Same number in another owner scope validates clean.
    $otherOwnerResult = OwnerContext::withOwner($ownerB, fn (): bool => Validator::make(
        ['doc_number' => 'INV-SHARED-1'],
        ['doc_number' => filamentDocs_scopedDocNumberRule()],
    )->passes());

    expect($otherOwnerResult)->toBeTrue();

    // Same number in the same owner scope still fails.
    $sameOwnerResult = OwnerContext::withOwner($ownerA, fn (): bool => Validator::make(
        ['doc_number' => 'INV-SHARED-1'],
        ['doc_number' => filamentDocs_scopedDocNumberRule()],
    )->passes());

    expect($sameOwnerResult)->toBeFalse();
});

it('keeps global uniqueness when owner scoping is disabled (H4)', function (): void {
    config()->set('docs.owner.enabled', false);

    Doc::factory()->create(['doc_number' => 'INV-GLOBAL-1']);

    $passes = Validator::make(
        ['doc_number' => 'INV-GLOBAL-1'],
        ['doc_number' => filamentDocs_scopedDocNumberRule()],
    )->passes();

    expect($passes)->toBeFalse();

    $freshPasses = Validator::make(
        ['doc_number' => 'INV-GLOBAL-2'],
        ['doc_number' => filamentDocs_scopedDocNumberRule()],
    )->passes();

    expect($freshPasses)->toBeTrue();
});

it('scopes global rows to null owners in explicit global context (H4)', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    $owner = User::query()->create([
        'name' => 'Number Owner',
        'email' => 'number-owner@example.test',
        'password' => bcrypt('password'),
    ]);

    OwnerContext::withOwner(null, fn (): Doc => Doc::factory()->create(['doc_number' => 'INV-GLOBAL-3']));
    OwnerContext::withOwner($owner, fn (): Doc => Doc::factory()->create(['doc_number' => 'INV-OWNED-3']));

    // Explicit global context only collides with other global rows.
    $globalResult = OwnerContext::withOwner(null, function (): array {
        $duplicateGlobal = Validator::make(
            ['doc_number' => 'INV-GLOBAL-3'],
            ['doc_number' => filamentDocs_scopedDocNumberRule()],
        )->passes();

        $ownedNumberIsFree = Validator::make(
            ['doc_number' => 'INV-OWNED-3'],
            ['doc_number' => filamentDocs_scopedDocNumberRule()],
        )->passes();

        return [$duplicateGlobal, $ownedNumberIsFree];
    });

    expect($globalResult)->toBe([false, true]);
});
