<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentPricing\Resources\PriceListResource;
use AIArmada\FilamentPricing\Resources\PromotionResource;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Database\Eloquent\Model;

uses(TestCase::class);

beforeEach(function (): void {
    if (! class_exists(Promotion::class)) {
        $this->markTestSkipped('Promotions package is not installed.');
    }
});

it('scopes filament pricing resources to the resolved owner (including global)', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.include_global', true);
    config()->set('promotions.features.owner.enabled', true);
    config()->set('promotions.features.owner.include_global', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-pricing@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-pricing@example.com',
        'password' => 'secret',
    ]);

    $bindOwner = static function (?Model $owner): void {
        app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface
        {
            public function __construct(private readonly ?Model $owner) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });
    };

    $bindOwner(null);

    $globalList = OwnerContext::withOwner(null, static fn () => PriceList::create([
        'name' => 'Global',
        'slug' => 'global',
        'currency' => 'MYR',
        'owner_type' => null,
        'owner_id' => null,
    ]));

    $globalPromo = OwnerContext::withOwner(null, static fn () => Promotion::create([
        'name' => 'Global Promo',
        'code' => 'GLOBAL-P',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
        'owner_type' => null,
        'owner_id' => null,
    ]));

    $bindOwner($ownerA);

    $ownerAList = OwnerContext::withOwner($ownerA, static fn () => PriceList::create([
        'name' => 'A',
        'slug' => 'a',
        'currency' => 'MYR',
    ]));

    $ownerAPromo = OwnerContext::withOwner($ownerA, static fn () => Promotion::create([
        'name' => 'A Promo',
        'code' => 'A-P',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
    ]));

    $bindOwner($ownerB);

    $ownerBList = OwnerContext::withOwner($ownerB, static fn () => PriceList::create([
        'name' => 'B',
        'slug' => 'b',
        'currency' => 'MYR',
    ]));

    $ownerBPromo = OwnerContext::withOwner($ownerB, static fn () => Promotion::create([
        'name' => 'B Promo',
        'code' => 'B-P',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
    ]));

    $bindOwner($ownerA);

    $priceListIds = PriceListResource::getEloquentQuery()->pluck('id')->all();
    expect($priceListIds)->toContain($globalList->id, $ownerAList->id)
        ->not->toContain($ownerBList->id);

    $promotionIds = PromotionResource::getEloquentQuery()->pluck('id')->all();
    expect($promotionIds)->toContain($globalPromo->id, $ownerAPromo->id)
        ->not->toContain($ownerBPromo->id);
});

it('returns strict global-only when owner resolver returns null', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('promotions.features.owner.enabled', true);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $globalList = OwnerContext::withOwner(null, static fn () => PriceList::create([
        'name' => 'Global 2',
        'slug' => 'global-2',
        'currency' => 'MYR',
        'owner_type' => null,
        'owner_id' => null,
    ]));

    $scopedIds = PriceListResource::getEloquentQuery()->pluck('id')->all();

    expect($scopedIds)->toContain($globalList->id);
});

it('returns strict global-only promotions when owner resolver returns null', function (): void {
    config()->set('promotions.features.owner.enabled', true);

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $globalPromotion = OwnerContext::withOwner(null, static fn () => Promotion::create([
        'name' => 'Global Promo 2',
        'code' => 'GLOBAL-2',
        'type' => 'percentage',
        'discount_value' => 10,
        'is_active' => true,
        'owner_type' => null,
        'owner_id' => null,
    ]));

    $scopedIds = PromotionResource::getEloquentQuery()->pluck('id')->all();

    expect($scopedIds)->toContain($globalPromotion->id);
});
