<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Models\PriceList;

describe('Pricing owner scope consolidation', function (): void {
    beforeEach(function (): void {
        config()->set('pricing.features.owner.enabled', true);
        config()->set('pricing.features.owner.include_global', false);
    });

    it('adapter class is removed', function (): void {
        expect(class_exists('AIArmada\Pricing\Support\PricingOwnerScope'))->toBeFalse();
    });

    it('PriceList is owner-scoped via HasOwner global scope', function (): void {
        $owner = User::query()->create([
            'name' => 'Pricing Cons. Owner',
            'email' => 'pricing-cons-owner@example.com',
            'password' => 'secret',
        ]);

        $owned = OwnerContext::withOwner($owner, fn () => PriceList::query()->create([
            'name' => 'Owned List',
            'slug' => 'owned-list',
            'currency' => 'MYR',
        ]));

        $global = OwnerContext::withOwner(null, fn () => PriceList::query()->create([
            'name' => 'Global List',
            'slug' => 'global-list',
            'currency' => 'MYR',
        ]));

        OwnerContext::withOwner($owner, function () use ($owned, $global): void {
            $ids = PriceList::query()->pluck('id');
            expect($ids)->toContain($owned->id);
            expect($ids)->not->toContain($global->id);
        });
    });
});
