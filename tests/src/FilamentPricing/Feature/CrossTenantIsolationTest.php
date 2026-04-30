<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentPricing\Widgets\PricingStatsWidget;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Promotions\Models\Promotion;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    if (! class_exists(Promotion::class)) {
        $this->markTestSkipped('Promotions package is not installed.');
    }
});

function bindFilamentPricingOwner(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

it('scopes filament-pricing dashboard stats to the current owner (optionally including global)', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.include_global', true);
    config()->set('promotions.features.owner.enabled', true);
    config()->set('promotions.features.owner.include_global', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'filament-pricing-owner-a-xt@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'filament-pricing-owner-b-xt@example.com',
        'password' => 'secret',
    ]);

    // Global rows are created with no owner context.
    bindFilamentPricingOwner(null);

    OwnerContext::withOwner(null, static function (): void {
        PriceList::query()->create([
            'name' => 'Global List',
            'slug' => 'global-list-xt',
            'currency' => 'MYR',
            'is_active' => true,
            'owner_type' => null,
            'owner_id' => null,
        ]);
    });

    $globalPromotion = OwnerContext::withOwner(null, static function (): Promotion {
        $promotion = Promotion::query()->create([
            'name' => 'Global Promo',
            'code' => 'GLOBAL-XT',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
            'owner_type' => null,
            'owner_id' => null,
        ]);

        $promotion->forceFill(['usage_count' => 7])->save();

        return $promotion;
    });

    bindFilamentPricingOwner($ownerA);

    OwnerContext::withOwner($ownerA, static function (): void {
        PriceList::query()->create([
            'name' => 'Owner A List',
            'slug' => 'owner-a-list-xt',
            'currency' => 'MYR',
            'is_active' => true,
        ]);
    });

    $ownerAPromotion = OwnerContext::withOwner($ownerA, static function (): Promotion {
        $promotion = Promotion::query()->create([
            'name' => 'Owner A Promo',
            'code' => 'A-XT',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $promotion->forceFill(['usage_count' => 3])->save();

        return $promotion;
    });

    bindFilamentPricingOwner($ownerB);

    OwnerContext::withOwner($ownerB, static function (): void {
        PriceList::query()->create([
            'name' => 'Owner B List',
            'slug' => 'owner-b-list-xt',
            'currency' => 'MYR',
            'is_active' => true,
        ]);
    });

    $ownerBPromotion = OwnerContext::withOwner($ownerB, static function (): Promotion {
        $promotion = Promotion::query()->create([
            'name' => 'Owner B Promo',
            'code' => 'B-XT',
            'type' => 'percentage',
            'discount_value' => 10,
            'is_active' => true,
        ]);

        $promotion->forceFill(['usage_count' => 5])->save();

        return $promotion;
    });

    bindFilamentPricingOwner($ownerA);

    $widget = app(PricingStatsWidget::class);

    $reflection = new ReflectionClass($widget);
    $method = $reflection->getMethod('getStats');

    /** @var array<int, Stat> $stats */
    $stats = $method->invoke($widget);

    expect($stats[0]->getValue())->toBe('2')
        ->and($stats[1]->getValue())->toBe('2')
        ->and($stats[2]->getValue())->toBe('10');
});
