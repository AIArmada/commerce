<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentPricing\Support\LikePattern;
use AIArmada\Products\Models\Product;

uses(TestCase::class);

it('escapes like wildcards so they match literally', function (): void {
    expect(LikePattern::escape('100%'))->toBe('100\\%')
        ->and(LikePattern::escape('a_b'))->toBe('a\\_b')
        ->and(LikePattern::escape('plain'))->toBe('plain');
});

it('still matches plain substrings through escaped patterns', function (): void {
    Product::create(['name' => 'Cotton Tee', 'price' => 1000]);

    $matches = Product::query()
        ->where('name', 'like', '%' . LikePattern::escape('Cotton') . '%')
        ->pluck('name')
        ->all();

    expect($matches)->toBe(['Cotton Tee']);
});
