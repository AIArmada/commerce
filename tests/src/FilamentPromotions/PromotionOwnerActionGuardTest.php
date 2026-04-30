<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentPromotions\Models\Promotion;
use AIArmada\Promotions\Support\PromotionsOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    config()->set('promotions.features.owner.enabled', true);
    config()->set('promotions.features.owner.include_global', false);
});

it('rejects cross-owner promotions for destructive actions', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Promotion Owner A',
        'email' => 'promotion-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Promotion Owner B',
        'email' => 'promotion-owner-b@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $promotionB = OwnerContext::withOwner($ownerB, static fn (): Promotion => Promotion::factory()->create([
        'name' => 'Owner B Promotion',
        'code' => null,
    ]));

    expect(fn () => OwnerWriteGuard::findOrFailForOwner(
        Promotion::class,
        (string) $promotionB->getKey(),
        PromotionsOwnerScope::resolveOwner(),
        includeGlobal: false,
        message: 'Promotion is not accessible in the current owner scope.',
    ))->toThrow(AuthorizationException::class, 'Promotion is not accessible in the current owner scope.');
});

it('rejects owned promotions when owner context is missing', function (): void {
    $owner = User::query()->create([
        'name' => 'Promotion Owner Missing Context',
        'email' => 'promotion-owner-missing-context@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    $ownedPromotion = OwnerContext::withOwner($owner, static fn (): Promotion => Promotion::factory()->create([
        'name' => 'Owned Promotion',
        'code' => null,
    ]));

    expect(fn () => OwnerWriteGuard::findOrFailForOwner(
        Promotion::class,
        (string) $ownedPromotion->getKey(),
        PromotionsOwnerScope::resolveOwner(),
        includeGlobal: false,
        message: 'Promotion is not accessible in the current owner scope.',
    ))->toThrow(AuthorizationException::class, 'Promotion is not accessible in the current owner scope.');
});

it('allows global promotions when owner context is missing', function (): void {
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver(null));

    $globalPromotion = OwnerContext::withOwner(null, static fn (): Promotion => Promotion::factory()->create([
        'name' => 'Global Promotion',
        'code' => null,
    ]));

    $authorized = OwnerWriteGuard::findOrFailForOwner(
        Promotion::class,
        (string) $globalPromotion->getKey(),
        PromotionsOwnerScope::resolveOwner(),
        includeGlobal: false,
        message: 'Promotion is not accessible in the current owner scope.',
    );

    expect((string) $authorized->getKey())->toBe((string) $globalPromotion->getKey());
});
