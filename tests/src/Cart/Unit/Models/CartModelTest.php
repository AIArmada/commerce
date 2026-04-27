<?php

declare(strict_types=1);

use AIArmada\Cart\Models\CartModel;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('cart.owner.enabled', true);
    OwnerContext::clearOverride();
    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });
    CartModel::query()->withoutOwnerScope()->delete();
});

it('blocks saving without an owner context when owner scoping is enabled', function (): void {
    expect(static function (): void {
        CartModel::create([
            'identifier' => 'guest-session-1',
            'instance' => 'default',
            'items' => [],
            'conditions' => [],
            'metadata' => [],
            'version' => 1,
        ]);
    })->toThrow(RuntimeException::class);
});

it('allows saving within an explicit global owner context when owner scoping is enabled', function (): void {
    $cart = OwnerContext::withOwner(null, static function (): CartModel {
        return CartModel::create([
            'identifier' => 'global-session-1',
            'instance' => 'default',
            'items' => [],
            'conditions' => [],
            'metadata' => [],
            'version' => 1,
        ]);
    });

    expect($cart->owner_type)->toBeNull();
    expect($cart->owner_id)->toBeNull();
});

it('assigns the resolved owner when saving under an owner context', function (): void {
    $owner = User::query()->create([
        'name' => 'Owner',
        'email' => 'owner@example.com',
        'password' => 'secret',
    ]);

    $cart = OwnerContext::withOwner($owner, static function (): CartModel {
        return CartModel::create([
            'identifier' => 'owner-session-1',
            'instance' => 'default',
            'items' => [],
            'conditions' => [],
            'metadata' => [],
            'version' => 1,
        ]);
    });

    expect($cart->owner_type)->toBe($owner->getMorphClass());
    expect((string) $cart->owner_id)->toBe((string) $owner->getKey());
});

it('rejects saving with an explicit owner that mismatches the current owner context', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'cart-model-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'cart-model-owner-b@example.com',
        'password' => 'secret',
    ]);

    expect(fn () => OwnerContext::withOwner($ownerA, static function () use ($ownerB): void {
        CartModel::create([
            'identifier' => 'mismatched-owner-cart',
            'instance' => 'default',
            'items' => [],
            'conditions' => [],
            'metadata' => [],
            'version' => 1,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);
    }))->toThrow(AuthorizationException::class, 'Cross-owner save blocked for AIArmada\\Cart\\Models\\CartModel.');
});
