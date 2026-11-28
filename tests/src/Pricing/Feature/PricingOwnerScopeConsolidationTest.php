<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Actions\CreatePriceList;
use AIArmada\Pricing\Actions\DeletePriceList;
use AIArmada\Pricing\Actions\EnsureListPrice;
use AIArmada\Pricing\Actions\SavePrice;
use AIArmada\Pricing\Actions\SavePriceTier;
use AIArmada\Pricing\Actions\UpdatePriceList;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;

describe('Pricing owner scope consolidation', function (): void {
    beforeEach(function (): void {
        config()->set('pricing.features.owner.enabled', true);
        config()->set('pricing.features.owner.include_global', false);
    });

    it('PriceList is owner-scoped via HasOwner global scope', function (): void {
        $owner = User::query()->create([
            'name' => 'Pricing Cons. Owner',
            'email' => 'pricing-cons-owner@example.com',
            'password' => 'secret',
        ]);

        $owned = OwnerContext::withOwner($owner, fn () => PriceList::query()->create([
            'name' => 'Owned List',
            'slug' => 'owned-list',
            'currency' => 'MYR',
        ]));

        $global = OwnerContext::withOwner(null, fn () => PriceList::query()->create([
            'name' => 'Global List',
            'slug' => 'global-list',
            'currency' => 'MYR',
        ]));

        OwnerContext::withOwner($owner, function () use ($owned, $global): void {
            $ids = PriceList::query()->pluck('id');
            expect($ids)->toContain($owned->id);
            expect($ids)->not->toContain($global->id);
        });
    });
});

it('keeps tenant-visible global pricing records outside the mutation scope', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.include_global', true);

    $owner = User::query()->create([
        'name' => 'Pricing Write Owner',
        'email' => 'pricing-write-owner@example.com',
        'password' => 'secret',
    ]);

    [$list, $price, $tier] = OwnerContext::withOwner(null, static function (): array {
        $product = Product::query()->create(['name' => 'Global Priceable', 'price' => 1000]);
        $list = app(CreatePriceList::class)->execute(['name' => 'Global Write List', 'slug' => 'global-write-list', 'currency' => 'MYR']);
        $price = app(SavePrice::class)->create([
            'price_list_id' => $list->id,
            'priceable_type' => $product->getMorphClass(),
            'priceable_id' => $product->id,
            'amount' => 1000,
        ]);
        $tier = app(SavePriceTier::class)->create([
            'price_list_id' => $list->id,
            'tierable_type' => $product->getMorphClass(),
            'tierable_id' => $product->id,
            'amount' => 900,
            'min_quantity' => 10,
        ]);

        return [$list, $price, $tier];
    });

    OwnerContext::withOwner($owner, static function () use ($list, $price, $tier): void {
        expect(PriceList::query()->whereKey($list->id)->exists())->toBeTrue()
            ->and(fn () => app(UpdatePriceList::class)->execute($list, ['name' => 'Changed']))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(DeletePriceList::class)->execute($list))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(SavePrice::class)->update($price, ['amount' => 1]))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(SavePrice::class)->delete($price))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(SavePriceTier::class)->update($tier, ['amount' => 1]))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(SavePriceTier::class)->delete($tier))->toThrow(ModelNotFoundException::class)
            ->and(fn () => app(EnsureListPrice::class)->execute($list, $price->priceable_type, $price->priceable_id, 1, null, 'MYR'))->toThrow(ModelNotFoundException::class);
    });

    OwnerContext::withOwner(null, static function () use ($list, $price, $tier): void {
        expect($list->fresh()->name)->toBe('Global Write List')
            ->and($price->fresh()->amount)->toBe(1000)
            ->and($tier->fresh()->amount)->toBe(900);
    });
});

it('assigns the active pricing owner when automatic assignment is disabled', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.auto_assign_on_create', false);

    $owner = User::query()->create([
        'name' => 'Explicit Pricing Owner',
        'email' => 'explicit-pricing-owner@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($owner, static function () use ($owner): void {
        $list = app(CreatePriceList::class)->execute(['name' => 'Explicit List', 'slug' => 'explicit-list', 'currency' => 'MYR']);

        $product = Product::query()->create(['name' => 'Explicit Priceable', 'price' => 1000]);
        $ensure = app(EnsureListPrice::class);
        $price = $ensure->execute($list, $product->getMorphClass(), $product->id, 900, 1000, 'MYR');
        $updated = $ensure->execute($list, $product->getMorphClass(), $product->id, 800, null, 'MYR');

        expect($list->owner_id)->toBe($owner->getKey())
            ->and($price->owner_id)->toBe($owner->getKey())
            ->and($updated->id)->toBe($price->id)
            ->and($updated->amount)->toBe(800)
            ->and($updated->compare_amount)->toBeNull()
            ->and(Price::query()->forOwner()->where('price_list_id', $list->id)->count())->toBe(1);
    });
});

it('rolls back cancelled pricing writes including default list demotion', function (): void {
    config()->set('pricing.features.owner.enabled', false);

    $list = app(CreatePriceList::class)->execute([
        'name' => 'Original default',
        'slug' => 'original-default',
        'currency' => 'MYR',
        'is_default' => true,
    ]);

    $product = Product::query()->create(['name' => 'Pricing cancellation product', 'price' => 1000]);
    $price = app(EnsureListPrice::class)->execute($list, $product->getMorphClass(), $product->id, 900, null, 'MYR');

    PriceList::creating(static fn (PriceList $record): bool => $record->slug !== 'cancelled-default');
    Price::updating(static fn (Price $record): bool => $record->getKey() !== $price->getKey());

    expect(fn () => app(CreatePriceList::class)->execute([
        'name' => 'Cancelled default',
        'slug' => 'cancelled-default',
        'currency' => 'MYR',
        'is_default' => true,
    ]))->toThrow(RuntimeException::class, 'cancelled')
        ->and(fn () => app(SavePrice::class)->update($price, ['amount' => 100]))
        ->toThrow(RuntimeException::class, 'cancelled')
        ->and(fn () => app(EnsureListPrice::class)->execute($list, $product->getMorphClass(), $product->id, 100, null, 'MYR'))
        ->toThrow(RuntimeException::class, 'cancelled')
        ->and($list->fresh()->is_default)->toBeTrue()
        ->and($price->fresh()->amount)->toBe(900);
});
