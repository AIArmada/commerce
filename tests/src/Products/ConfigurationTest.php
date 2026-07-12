<?php

declare(strict_types=1);

it('declares single-file limits for single-image media collections', function (): void {
    expect(config('products.media.collections.hero.limit'))->toBe(1)
        ->and(config('products.media.collections.icon.limit'))->toBe(1)
        ->and(config('products.media.collections.banner.limit'))->toBe(1);
});
