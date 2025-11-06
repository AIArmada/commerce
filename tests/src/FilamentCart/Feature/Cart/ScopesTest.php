<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\Cart;

it('filters active carts with scopeNotEmpty', function (): void {
    $empty = Cart::factory()->empty()->create();
    $filled = Cart::factory()->create([
        'items_count' => 2,
        'quantity' => 3,
    ]);

    $results = Cart::query()->notEmpty()->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($filled->id);
    expect($results->contains($empty))->toBeFalse();
});

it('limits carts by recency', function (): void {
    $recent = Cart::factory()->create(['updated_at' => now()]);
    Cart::factory()->create(['updated_at' => now()->subDays(10)]);

    $results = Cart::query()->recent(7)->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($recent->id);
});
