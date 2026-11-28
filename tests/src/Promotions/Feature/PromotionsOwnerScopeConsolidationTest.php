<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Promotions\Actions\DeactivatePromotion;
use AIArmada\Promotions\Actions\DeletePromotion;
use Illuminate\Database\Eloquent\Collection;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Auth\Access\AuthorizationException;

describe('Promotions owner scope consolidation', function (): void {
    beforeEach(function (): void {
        config()->set('promotions.features.owner.enabled', true);
        config()->set('promotions.features.owner.include_global', false);
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

    it('locks only the exact writable owner when global promotions are readable', function (): void {
        config()->set('promotions.features.owner.include_global', true);

        $owner = User::query()->create([
            'name' => 'Promotion Lock Owner',
            'email' => 'promotion-lock-owner@example.com',
            'password' => 'secret',
        ]);

        $owned = OwnerContext::withOwner($owner, fn () => Promotion::factory()->active()->create());
        $global = OwnerContext::withOwner(null, fn () => Promotion::factory()->active()->create());

        OwnerContext::withOwner($owner, function () use ($owned, $global): void {
            expect(Promotion::query()->whereKey($global->getKey())->exists())->toBeTrue();
            expect(fn () => app(DeactivatePromotion::class)->handle($global))->toThrow(AuthorizationException::class);
            expect(app(DeactivatePromotion::class)->handle($owned)->is_active)->toBeFalse();
        });

        expect(OwnerContext::withOwner(null, fn () => $global->fresh()->is_active))->toBeTrue();
    });
});

it('rolls back an entire promotion deletion batch when one record is outside the owner scope', function (): void {
    config()->set('promotions.features.owner.enabled', true);
    config()->set('promotions.features.owner.include_global', true);

    $owner = User::factory()->create();
    $owned = OwnerContext::withOwner($owner, fn (): Promotion => Promotion::factory()->create([
        'id' => '00000000-0000-7000-8000-000000000001',
    ]));
    $global = OwnerContext::withOwner(null, fn (): Promotion => Promotion::factory()->create([
        'id' => '00000000-0000-7000-8000-000000000002',
    ]));

    OwnerContext::withOwner($owner, function () use ($owned, $global): void {
        expect(fn () => app(DeletePromotion::class)->handleMany(new Collection([$owned, $global])))
            ->toThrow(AuthorizationException::class);

        expect(Promotion::query()->whereKey($owned->id)->exists())->toBeTrue();
    });

    expect(OwnerContext::withOwner(null, fn () => Promotion::query()->whereKey($global->id)->exists()))->toBeTrue();
});
