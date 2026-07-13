<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\Docs\Models\DocTemplate;
use Illuminate\Database\QueryException;

it('enforces deterministic global template uniqueness', function (): void {
    OwnerContext::withOwner(null, function (): void {
        DocTemplate::query()->create([
            'name' => 'Global Invoice', 'slug' => 'global-invoice', 'doc_type' => 'invoice', 'layout' => [],
        ]);

        expect(fn () => DocTemplate::query()->create([
            'name' => 'Duplicate Global Invoice', 'slug' => 'global-invoice', 'doc_type' => 'invoice', 'layout' => [],
        ]))->toThrow(QueryException::class);

        expect(DocTemplate::query()->globalOnly()->where('slug', 'global-invoice')->sole()->owner_scope)
            ->toBe(OwnerScopeKey::GLOBAL);
    });
});
