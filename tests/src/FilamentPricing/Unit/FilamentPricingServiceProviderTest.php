<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentPricing\FilamentPricingServiceProvider;

uses(TestCase::class);

it('boots and registers without errors', function (): void {
    $provider = new FilamentPricingServiceProvider(app());

    $provider->register();
    $provider->boot();

    expect(true)->toBeTrue();
});
