<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\FilamentSignals\FilamentSignalsPlugin;
use AIArmada\FilamentSignals\FilamentSignalsServiceProvider;
use Mockery\MockInterface;
use Spatie\LaravelPackageTools\Package;

uses(FilamentSignalsTestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('configures the package name and config file', function (): void {
    /** @var Package&MockInterface $package */
    $package = Mockery::mock(Package::class);
    $package->shouldReceive('name')->once()->with('filament-signals')->andReturnSelf();
    $package->shouldReceive('hasConfigFile')->once()->withNoArgs()->andReturnSelf();
    $package->shouldReceive('hasViews')->once()->withNoArgs()->andReturnSelf();

    $provider = new FilamentSignalsServiceProvider(app());
    $provider->configurePackage($package);
});

it('registers the filament signals plugin singleton', function (): void {
    app()->register(FilamentSignalsServiceProvider::class);

    expect(app()->bound(FilamentSignalsPlugin::class))->toBeTrue()
        ->and(app(FilamentSignalsPlugin::class))->toBeInstanceOf(FilamentSignalsPlugin::class);
});
