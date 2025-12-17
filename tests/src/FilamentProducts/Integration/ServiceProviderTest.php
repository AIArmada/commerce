<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentProducts\FilamentProductsServiceProvider;

uses(TestCase::class);

it('boots the filament products service provider', function (): void {
    $provider = new FilamentProductsServiceProvider(app());

    $provider->register();
    $provider->boot();

    expect(true)->toBeTrue();
});
