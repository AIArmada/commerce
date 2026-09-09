<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;

uses(TestCase::class);

use AIArmada\FilamentPricing\Widgets\PricingStatsWidget;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Tests\Concerns\EnsuresPricingSchema;
use AIArmada\Promotions\Models\Promotion;
use Filament\Widgets\StatsOverviewWidget\Stat;

uses(EnsuresPricingSchema::class);

beforeEach(function (): void {
    $this->ensurePricingSchema();
});

beforeEach(function (): void {
    if (! class_exists(Promotion::class)) {
        $this->markTestSkipped('Promotions package is not installed.');
    }
});

it('builds pricing stats with correct counts', function (): void {
    PriceList::query()->create([
        'name' => 'List A',
        'slug' => 'list-a',
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    PriceList::query()->create([
        'name' => 'List Inactive',
        'slug' => 'list-inactive',
        'currency' => 'MYR',
        'is_active' => false,
    ]);

    $activePromotion = Promotion::query()->create([
        'name' => 'Promo A',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
    ]);
    $activePromotion->forceFill(['usage_count' => 3])->save();

    $inactivePromotion = Promotion::query()->create([
        'name' => 'Promo Inactive',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => false,
    ]);
    $inactivePromotion->forceFill(['usage_count' => 5])->save();

    $widget = app(PricingStatsWidget::class);

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getStats');

    /** @var array<int, Stat> $stats */
    $stats = $method->invoke($widget);

    expect($stats)->toHaveCount(3)
        ->and($stats[0]->getLabel())->toBe('Active Price Lists')
        ->and($stats[0]->getValue())->toBe('1')
        ->and($stats[1]->getLabel())->toBe('Active Promotions')
        ->and($stats[1]->getValue())->toBe('1')
        ->and($stats[2]->getLabel())->toBe('Promotion Uses')
        ->and($stats[2]->getValue())->toBe('8');
});

it('uses the promotions feature owner settings and excludes global rows by default', function (): void {
    config([
        'promotions.features.owner.enabled' => true,
        'promotions.features.owner.include_global' => false,
        'promotions.owner.enabled' => false,
    ]);

    $owner = User::query()->create([
        'name' => 'Widget Owner',
        'email' => 'pricing-widget-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $globalPromotion = Promotion::make([
        'name' => 'Global Widget Promotion',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
    ]);
    $globalPromotion->forceFill(['owner_type' => null, 'owner_id' => null]);
    $globalPromotion->saveQuietly();

    $ownedPromotion = Promotion::make([
        'name' => 'Owned Widget Promotion',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
    ]);
    $ownedPromotion->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);
    $ownedPromotion->saveQuietly();

    $stats = OwnerContext::withOwner($owner, function (): array {
        $widget = app(PricingStatsWidget::class);
        $method = (new ReflectionClass($widget))->getMethod('getStats');

        /** @var array<int, Stat> $stats */
        return $method->invoke($widget);
    });

    expect($stats[1]->getValue())->toBe('1');
});
