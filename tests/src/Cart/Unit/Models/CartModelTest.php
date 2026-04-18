<?php

declare(strict_types=1);

use AIArmada\Cart\Models\CartModel;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('cart.owner.enabled', true);
    OwnerContext::override(null);
    CartModel::query()->delete();
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
