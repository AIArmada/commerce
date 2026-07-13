<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\Shipping\Models\ShippingZone;
use Illuminate\Database\QueryException;

it('enforces deterministic global shipping zone uniqueness', function (): void {
    config()->set('shipping.features.owner.enabled', true);

    OwnerContext::withOwner(null, function (): void {
        ShippingZone::query()->create(['name' => 'Global Zone', 'code' => 'GLOBAL', 'type' => 'country']);
        expect(fn () => ShippingZone::query()->create([
            'name' => 'Duplicate Global Zone', 'code' => 'GLOBAL', 'type' => 'country',
        ]))->toThrow(QueryException::class);
        expect(ShippingZone::query()->globalOnly()->where('code', 'GLOBAL')->sole()->owner_scope)
            ->toBe(OwnerScopeKey::GLOBAL);
    });
});
