<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Tax\Contracts\TaxCalculatorInterface;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Services\ZoneResolver\DefaultZoneResolver;
use AIArmada\Tax\Settings\TaxSettings;

describe('Tax owner scope consolidation', function (): void {
    beforeEach(function (): void {
        config()->set('tax.features.owner.enabled', true);
        config()->set('tax.features.owner.include_global', false);
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

it('does not reuse a globally visible cached zone when global matching is suppressed', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', true);
    $owner = User::factory()->create();

    $global = OwnerContext::withOwner(null, fn () => TaxZone::query()->create([
        'name' => 'Global Default',
        'code' => 'GLOBAL-DEFAULT-CACHE',
        'is_default' => true,
        'is_active' => true,
    ]));

    OwnerContext::withOwner($owner, function () use ($global): void {
        $resolver = new DefaultZoneResolver;
        expect($resolver->resolve(null, [])?->is($global))->toBeTrue();

        OwnerScopeOverride::withoutIncludeGlobal(function () use ($resolver): void {
            expect($resolver->resolve(null, []))->toBeNull();
        });

        expect($resolver->resolve(null, [])?->is($global))->toBeTrue();
    });
});

it('requires owner context even when shipping tax is disabled', function (): void {
    config([
        'tax.features.owner.enabled' => true,
        'tax.defaults.calculate_tax_on_shipping' => false,
    ]);

    app()->forgetInstance(OwnerResolverInterface::class);
    app()->offsetUnset(OwnerResolverInterface::class);
    app()->bind(TaxSettings::class, fn () => throw new RuntimeException('Use static tax configuration.'));

    $calculator = app(TaxCalculatorInterface::class);

    expect(fn () => $calculator->calculateShippingTax(5000))
        ->toThrow(NoCurrentOwnerException::class);

    $result = OwnerContext::withOwner(null, fn () => $calculator->calculateShippingTax(5000));

    expect($result->taxAmount)->toBe(0);
});
