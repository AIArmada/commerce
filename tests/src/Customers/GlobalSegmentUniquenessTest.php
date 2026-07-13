<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\Customers\Models\Segment;
use Illuminate\Database\QueryException;

it('enforces deterministic global customer segment uniqueness', function (): void {
    config()->set('customers.features.owner.enabled', true);

    OwnerContext::withOwner(null, function (): void {
        Segment::query()->create(['name' => 'Global VIP', 'slug' => 'global-vip']);
        expect(fn () => Segment::query()->create(['name' => 'Global VIP 2', 'slug' => 'global-vip']))
            ->toThrow(QueryException::class);
        expect(Segment::query()->globalOnly()->where('slug', 'global-vip')->sole()->owner_scope)
            ->toBe(OwnerScopeKey::GLOBAL);
    });
});
