<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\TrackedPropertyResolver;

uses(SignalsTestCase::class);

it('resolves the single active property for an owner', function (): void {
    $owner = User::query()->firstOrFail();

    $property = TrackedProperty::query()->create([
        'name' => 'Primary Property',
        'slug' => 'primary-property',
        'write_key' => 'primary-property-key',
        'type' => 'website',
        'is_active' => true,
    ]);

    $resolver = app(TrackedPropertyResolver::class);

    expect($resolver->resolveForOwner($owner)?->is($property))->toBeTrue();
});

it('returns null when an owner has multiple active matching properties', function (): void {
    $owner = User::query()->firstOrFail();

    TrackedProperty::query()->create([
        'name' => 'Property One',
        'slug' => 'property-one',
        'write_key' => 'property-one-key',
        'type' => 'website',
        'is_active' => true,
    ]);

    TrackedProperty::query()->create([
        'name' => 'Property Two',
        'slug' => 'property-two',
        'write_key' => 'property-two-key',
        'type' => 'website',
        'is_active' => true,
    ]);

    $resolver = app(TrackedPropertyResolver::class);

    expect($resolver->resolveForOwner($owner))->toBeNull();
});

it('ignores properties belonging to other owners when resolving', function (): void {
    $owner = User::query()->firstOrFail();
    $otherOwner = User::query()->create([
        'name' => 'Other Property Owner',
        'email' => 'other-property-owner@signals.test',
        'password' => 'secret',
    ]);

    $property = TrackedProperty::query()->create([
        'name' => 'Owner Property',
        'slug' => 'owner-property',
        'write_key' => 'owner-property-key',
        'type' => 'website',
        'is_active' => true,
    ]);

    OwnerContext::withOwner($otherOwner, static function (): void {
        TrackedProperty::query()->create([
            'name' => 'Other Owner Property',
            'slug' => 'other-owner-property',
            'write_key' => 'other-owner-property-key',
            'type' => 'website',
            'is_active' => true,
        ]);
    });

    $resolver = app(TrackedPropertyResolver::class);

    expect($resolver->resolveForOwner($owner)?->is($property))->toBeTrue();
});

it('auto-creates a deterministic tracked property only for explicitly enabled integrations', function (): void {
    $owner = User::query()->firstOrFail();
    $resolver = app(TrackedPropertyResolver::class);

    expect($resolver->resolveForOwner($owner, integration: 'cart'))->toBeNull();

    config()->set('signals.integrations.cart.enabled', true);
    config()->set('signals.integrations.cart.tracked_property.auto_create', true);
    config()->set('signals.integrations.cart.tracked_property.slug', 'commerce-cart');
    config()->set('signals.integrations.cart.tracked_property.name', 'Commerce Cart');

    $property = $resolver->resolveForOwner($owner, integration: 'cart');

    expect($property)->toBeInstanceOf(TrackedProperty::class)
        ->and($property?->slug)->toBe('commerce-cart')
        ->and($property?->settings['integration'] ?? null)->toBe('cart')
        ->and($resolver->resolveForOwner($owner, integration: 'cart')?->is($property))->toBeTrue();
});
