<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Shipping\Models\ShippingZone;

describe('Shipping owner scope consolidation', function (): void {
    beforeEach(function (): void {
        config()->set('shipping.features.owner.enabled', true);
        config()->set('shipping.features.owner.include_global', false);
    });

    it('adapter class is removed', function (): void {
        expect(class_exists('AIArmada\Shipping\Support\ShippingOwnerScope'))->toBeFalse();
    });

    it('ShippingZone is owner-scoped via HasOwner global scope', function (): void {
        $owner = User::query()->create([
            'name' => 'Shipping Cons. Owner',
            'email' => 'ship-cons-owner@example.com',
            'password' => 'secret',
        ]);

        $owned = OwnerContext::withOwner($owner, fn () => ShippingZone::query()->create([
            'name' => 'Owned Zone',
            'code' => 'OWNED-ZONE',
            'type' => 'country',
        ]));

        $global = OwnerContext::withOwner(null, fn () => ShippingZone::query()->create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL-ZONE',
            'type' => 'country',
        ]));

        OwnerContext::withOwner($owner, function () use ($owned, $global): void {
            $ids = ShippingZone::query()->pluck('id');
            expect($ids)->toContain($owned->id);
            expect($ids)->not->toContain($global->id);
        });
    });
});
