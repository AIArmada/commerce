<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Contracts\Priceable;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Actions\ApplyPromotionalAdjustment;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\Pricing\Services\PriceCalculator;
use AIArmada\Pricing\Support\CustomerPriceResolver;
use AIArmada\Pricing\Support\PromotionalPriceResolver;
use AIArmada\Pricing\Support\SegmentPriceResolver;
use AIArmada\Pricing\Support\TierResolver;
use AIArmada\Pricing\Tests\Concerns\EnsuresPricingSchema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

uses(EnsuresPricingSchema::class);

beforeEach(function (): void {
    $this->ensurePricingSchema();
});

function bindPricingOwner(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?RecordReference
        {
            return EloquentRecordReference::fromModel($this->owner);
        }
    });
}

it('blocks cross-tenant reads and writes for owned price lists/prices', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'pricing-owner-a-xt@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'pricing-owner-b-xt@example.com',
        'password' => 'secret',
    ]);

    bindPricingOwner($ownerB);

    $listB = PriceList::query()->create([
        'name' => 'List B',
        'slug' => 'list-b-xt',
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    $item = new class implements Priceable
    {
        public function getBuyableIdentifier(): string
        {
            return 'item-xt';
        }

        public function getBasePrice(): int
        {
            return 10_000;
        }

        public function getComparePrice(): ?int
        {
            return null;
        }

        public function isOnSale(): bool
        {
            return false;
        }

        public function getDiscountPercentage(): ?float
        {
            return null;
        }
    };

    Price::query()->create([
        'price_list_id' => $listB->id,
        'priceable_type' => get_class($item),
        'priceable_id' => $item->getBuyableIdentifier(),
        'amount' => 5_000,
        'currency' => 'MYR',
    ]);

    bindPricingOwner($ownerA);

    $calculator = new PriceCalculator(
        new TierResolver,
        new PromotionalPriceResolver(new ApplyPromotionalAdjustment),
        new CustomerPriceResolver,
        new SegmentPriceResolver,
    );

    $result = $calculator->calculate($item, 1, ['price_list_id' => $listB->id]);

    expect($result->finalPrice)->toBe(10_000);

    expect(fn () => Price::query()->create([
        'price_list_id' => $listB->id,
        'priceable_type' => get_class($item),
        'priceable_id' => $item->getBuyableIdentifier(),
        'amount' => 4_000,
        'currency' => 'MYR',
    ]))
        ->toThrow(AuthorizationException::class);
});

it('blocks writing and deleting owned rows when owner is unresolved', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.include_global', false);

    $owner = User::query()->create([
        'name' => 'Owner',
        'email' => 'pricing-owner-null-context@example.com',
        'password' => 'secret',
    ]);

    // No owner context
    bindPricingOwner(null);

    $ownedList = new PriceList([
        'name' => 'Owned List',
        'slug' => 'owned-null-context',
        'currency' => 'MYR',
        'is_active' => true,
    ]);
    $ownedList->owner_type = $owner->getMorphClass();
    $ownedList->owner_id = (string) $owner->getKey();

    expect(fn () => $ownedList->save())
        ->toThrow(AuthorizationException::class);

    // Create an owned row under a real owner context...
    bindPricingOwner($owner);
    $list = PriceList::query()->create([
        'name' => 'List',
        'slug' => 'list-null-context',
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    // ...then ensure it cannot be deleted without owner context.
    bindPricingOwner(null);

    expect(fn () => $list->delete())
        ->toThrow(AuthorizationException::class);
});

it('auto-assigns the resolved owner to new pricing records when enabled', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.auto_assign_on_create', true);

    $owner = User::query()->create([
        'name' => 'Owner Auto Assign',
        'email' => 'pricing-owner-auto-assign@example.com',
        'password' => 'secret',
    ]);

    bindPricingOwner($owner);

    $priceList = PriceList::query()->create([
        'name' => 'Auto Assigned List',
        'slug' => 'auto-assigned-list',
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    $price = Price::query()->create([
        'price_list_id' => $priceList->id,
        'priceable_type' => 'TestProduct',
        'priceable_id' => 'auto-price-item',
        'amount' => 2_500,
        'currency' => 'MYR',
    ]);

    expect($priceList->owner_type)->toBe($owner->getMorphClass())
        ->and((string) $priceList->owner_id)->toBe((string) $owner->getKey())
        ->and($price->owner_type)->toBe($owner->getMorphClass())
        ->and((string) $price->owner_id)->toBe((string) $owner->getKey());
});

it('does not auto assign the resolved owner when auto assignment is disabled', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.auto_assign_on_create', false);

    $owner = User::query()->create([
        'name' => 'Owner No Auto Assign',
        'email' => 'pricing-owner-no-auto-assign@example.com',
        'password' => 'secret',
    ]);

    bindPricingOwner($owner);

    $priceList = PriceList::query()->create([
        'name' => 'Global From Owner Context',
        'slug' => 'global-from-owner-context',
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    expect($priceList->owner_type)->toBeNull()
        ->and($priceList->owner_id)->toBeNull();
});

it('rejects malformed owner tuples on pricing writes', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.auto_assign_on_create', false);

    $owner = User::query()->create([
        'name' => 'Owner Invalid Tuple',
        'email' => 'pricing-owner-invalid-tuple@example.com',
        'password' => 'secret',
    ]);

    bindPricingOwner(null);

    $priceList = new PriceList([
        'name' => 'Invalid List',
        'slug' => 'invalid-list',
        'currency' => 'MYR',
    ]);
    $priceList->owner_type = $owner->getMorphClass();
    $priceList->owner_id = null;

    expect(fn () => OwnerContext::withOwner(null, static fn () => $priceList->save()))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects cross-owner pricing references using morph aliases', function (): void {
    config([
        'pricing.features.owner.enabled' => true,
        'pricing.features.owner.include_global' => false,
        'pricing.features.owner.auto_assign_on_create' => true,
    ]);

    $ownerA = User::query()->create(['name' => 'Alias Owner A', 'email' => 'pricing-alias-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Alias Owner B', 'email' => 'pricing-alias-b@example.com', 'password' => 'secret']);
    bindPricingOwner($ownerB);

    $target = PriceList::query()->create(['name' => 'Target B', 'slug' => 'alias-target-b', 'currency' => 'MYR']);

    bindPricingOwner($ownerA);
    $list = PriceList::query()->create(['name' => 'List A', 'slug' => 'alias-list-a', 'currency' => 'MYR']);
    $previousMorphMap = Relation::morphMap();

    try {
        Relation::morphMap(['pricing-owner-test-list' => PriceList::class]);

        expect(fn () => Price::query()->create([
            'price_list_id' => $list->id,
            'priceable_type' => 'pricing-owner-test-list',
            'priceable_id' => $target->id,
            'amount' => 100,
            'currency' => 'MYR',
        ]))->toThrow(AuthorizationException::class);

        expect(fn () => PriceTier::query()->create([
            'price_list_id' => $list->id,
            'tierable_type' => 'pricing-owner-test-list',
            'tierable_id' => $target->id,
            'min_quantity' => 2,
            'amount' => 100,
            'currency' => 'MYR',
        ]))->toThrow(AuthorizationException::class);
    } finally {
        Relation::morphMap($previousMorphMap, false);
    }
});

it('rejects tenant price and tier writes into a visible global price list', function (): void {
    config([
        'pricing.features.owner.enabled' => true,
        'pricing.features.owner.include_global' => true,
        'pricing.features.owner.auto_assign_on_create' => true,
    ]);

    $owner = User::query()->create(['name' => 'Pricing Child Owner', 'email' => 'pricing-child-owner@example.com', 'password' => 'secret']);
    $global = OwnerContext::withOwner(null, fn (): PriceList => PriceList::query()->create(['name' => 'Shared Retail', 'slug' => 'shared-retail']));

    OwnerContext::withOwner($owner, function () use ($global, $owner): void {
        expect(PriceList::query()->whereKey($global->id)->exists())->toBeTrue();

        expect(fn () => Price::query()->create([
            'price_list_id' => $global->id,
            'priceable_type' => $owner->getMorphClass(),
            'priceable_id' => $owner->getKey(),
            'amount' => 100,
        ]))->toThrow(AuthorizationException::class);

        expect(fn () => PriceTier::query()->create([
            'price_list_id' => $global->id,
            'tierable_type' => $owner->getMorphClass(),
            'tierable_id' => $owner->getKey(),
            'min_quantity' => 2,
            'amount' => 100,
        ]))->toThrow(AuthorizationException::class);
    });

    expect(Price::query()->withoutOwnerScope()->where('price_list_id', $global->id)->count())->toBe(0)
        ->and(PriceTier::query()->withoutOwnerScope()->where('price_list_id', $global->id)->count())->toBe(0);
});
