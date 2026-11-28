<?php

declare(strict_types=1);

use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\Pricing\Support\TierResolver;

it('resolves one canonical active tier matrix', function (): void {
    config(['pricing.features.owner.enabled' => false]);

    $tierableType = 'TestProduct';
    $tierableId = 'tier-matrix-' . uniqid();
    $priceList = PriceList::create([
        'name' => 'Tier Matrix List',
        'slug' => 'tier-matrix-' . uniqid(),
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    PriceTier::create([
        'price_list_id' => $priceList->id,
        'tierable_type' => $tierableType,
        'tierable_id' => $tierableId,
        'min_quantity' => 10,
        'max_quantity' => null,
        'amount' => 800,
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    PriceTier::create([
        'price_list_id' => $priceList->id,
        'tierable_type' => $tierableType,
        'tierable_id' => $tierableId,
        'min_quantity' => 10,
        'max_quantity' => null,
        'amount' => 700,
        'currency' => 'MYR',
        'is_active' => false,
    ]);

    PriceTier::create([
        'tierable_type' => $tierableType,
        'tierable_id' => $tierableId,
        'min_quantity' => 10,
        'max_quantity' => null,
        'amount' => 900,
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    PriceTier::create([
        'tierable_type' => $tierableType,
        'tierable_id' => $tierableId,
        'min_quantity' => 20,
        'max_quantity' => null,
        'amount' => 600,
        'currency' => 'MYR',
        'is_active' => false,
    ]);

    $resolver = new TierResolver;

    expect($resolver->resolve($tierableType, $tierableId, 1, []))->toBeNull()
        ->and($resolver->resolve($tierableType, $tierableId, 10, [
            'price_list_id' => $priceList->id,
        ])?->price)->toBe(800)
        ->and($resolver->resolve($tierableType, $tierableId, 10, [])?->price)->toBe(900)
        ->and($resolver->resolve($tierableType, $tierableId, 20, [
            'price_list_id' => $priceList->id,
        ])?->price)->toBe(800);
});

it('ignores tiers belonging to inactive price lists', function (): void {
    config(['pricing.features.owner.enabled' => false]);

    $list = PriceList::create([
        'name' => 'Inactive tier list',
        'slug' => 'inactive-tier-list',
        'currency' => 'MYR',
        'is_active' => false,
    ]);

    PriceTier::create([
        'price_list_id' => $list->id,
        'tierable_type' => 'TestProduct',
        'tierable_id' => 'inactive-list-item',
        'min_quantity' => 2,
        'amount' => 100,
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    expect((new TierResolver)->resolve('TestProduct', 'inactive-list-item', 2, [
        'price_list_id' => $list->id,
    ]))->toBeNull();
});

it('does not resolve tiers from an ineligible targeted price list', function (): void {
    config(['pricing.features.owner.enabled' => false]);
    $segmentId = (string) Illuminate\Support\Str::uuid();
    $itemId = (string) Illuminate\Support\Str::uuid();
    $list = PriceList::create([
        'name' => 'Private Tier List',
        'slug' => 'private-tier-list',
        'segment_id' => $segmentId,
        'currency' => 'MYR',
    ]);
    PriceTier::create([
        'price_list_id' => $list->id,
        'tierable_type' => 'TestProduct',
        'tierable_id' => $itemId,
        'min_quantity' => 2,
        'amount' => 100,
        'is_active' => true,
    ]);

    $resolver = new TierResolver;

    expect($resolver->resolve('TestProduct', $itemId, 2, ['price_list_id' => $list->id]))->toBeNull()
        ->and($resolver->resolve('TestProduct', $itemId, 2, ['price_list_id' => $list->id, 'segment_ids' => [$segmentId]])?->price)->toBe(100);
});
