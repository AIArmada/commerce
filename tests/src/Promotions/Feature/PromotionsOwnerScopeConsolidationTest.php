<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Promotions\Models\Promotion;

describe('Promotions owner scope consolidation', function (): void {
    beforeEach(function (): void {
        config()->set('promotions.features.owner.enabled', true);
        config()->set('promotions.features.owner.include_global', false);
    });

    it('adapter class is removed', function (): void {
        expect(class_exists('AIArmada\Promotions\Support\PromotionsOwnerScope'))->toBeFalse();
    });

    it('Promotion is owner-scoped via HasOwner global scope', function (): void {
        $owner = User::query()->create([
            'name' => 'Promo Cons. Owner',
            'email' => 'promo-cons-owner@example.com',
            'password' => 'secret',
        ]);

        $owned = OwnerContext::withOwner($owner, fn () => Promotion::factory()->create([
            'name' => 'Owned Promo',
        ]));

        $global = OwnerContext::withOwner(null, fn () => Promotion::factory()->create([
            'name' => 'Global Promo',
        ]));

        OwnerContext::withOwner($owner, function () use ($owned, $global): void {
            $ids = Promotion::query()->pluck('id');
            expect($ids)->toContain($owned->id);
            expect($ids)->not->toContain($global->id);
        });
    });
});
