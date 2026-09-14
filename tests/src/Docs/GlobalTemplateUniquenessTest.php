<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\Docs\Models\DocTemplate;
use Illuminate\Database\QueryException;

it('enforces deterministic global template uniqueness', function (): void {
    OwnerContext::withOwner(null, function (): void {
        DocTemplate::query()->create([
            'name' => 'Global Invoice', 'slug' => 'global-invoice', 'doc_type' => 'invoice', 'layout' => [['type' => 'document_header', 'data' => []]],
        ]);

        expect(fn () => DocTemplate::query()->create([
            'name' => 'Duplicate Global Invoice', 'slug' => 'global-invoice', 'doc_type' => 'invoice', 'layout' => [['type' => 'document_header', 'data' => []]],
        ]))->toThrow(QueryException::class);

        expect(DocTemplate::query()->globalOnly()->where('slug', 'global-invoice')->sole()->owner_scope)
            ->toBe(OwnerScopeKey::GLOBAL);
    });
});

it('allows cross-owner slug reuse while keeping same-owner slugs unique', function (): void {
    config(['docs.owner.enabled' => true]);

    $ownerA = User::query()->create([
        'name' => 'Owner A', 'email' => 'owner-a-scope@example.test', 'password' => bcrypt('password'),
    ]);
    $ownerB = User::query()->create([
        'name' => 'Owner B', 'email' => 'owner-b-scope@example.test', 'password' => bcrypt('password'),
    ]);

    $attrs = [
        'name' => 'Shared', 'slug' => 'shared-slug', 'doc_type' => 'invoice',
        'layout' => [['type' => 'document_header', 'data' => []]],
    ];

    $templateA = OwnerContext::withOwner($ownerA, fn (): DocTemplate => DocTemplate::query()->create($attrs));
    $templateB = OwnerContext::withOwner($ownerB, fn (): DocTemplate => DocTemplate::query()->create($attrs));

    expect($templateA->owner_scope)->not->toBe(OwnerScopeKey::GLOBAL)
        ->and($templateB->owner_scope)->not->toBe(OwnerScopeKey::GLOBAL)
        ->and($templateA->owner_scope)->not->toBe($templateB->owner_scope);

    expect(fn (): mixed => OwnerContext::withOwner($ownerA, fn (): mixed => DocTemplate::query()->create($attrs)))
        ->toThrow(QueryException::class);
});
