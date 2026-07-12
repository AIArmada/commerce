<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Tax\Models\TaxZone;

describe('Tax owner scope consolidation', function (): void {
    beforeEach(function (): void {
        config()->set('tax.features.owner.enabled', true);
        config()->set('tax.features.owner.include_global', false);
    });

    it('adapter class is removed', function (): void {
        expect(class_exists('AIArmada\Tax\Support\TaxOwnerScope'))->toBeFalse();
    });

    it('TaxZone is owner-scoped via HasOwner global scope', function (): void {
        $owner = User::query()->create([
            'name' => 'Tax Cons. Owner',
            'email' => 'tax-cons-owner@example.com',
            'password' => 'secret',
        ]);

        $owned = OwnerContext::withOwner($owner, fn () => TaxZone::query()->create([
            'name' => 'Owned Zone',
            'code' => 'OWNED-ZONE',
            'type' => 'country',
        ]));

        $global = OwnerContext::withOwner(null, fn () => TaxZone::query()->create([
            'name' => 'Global Zone',
            'code' => 'GLOBAL-ZONE',
            'type' => 'country',
        ]));

        OwnerContext::withOwner($owner, function () use ($owned, $global): void {
            $ids = TaxZone::query()->pluck('id');
            expect($ids)->toContain($owned->id);
            expect($ids)->not->toContain($global->id);
        });
    });
});
