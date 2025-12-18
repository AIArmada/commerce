<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentJnt\FilamentJntTestCase;
use AIArmada\FilamentJnt\FilamentJntPlugin;
use AIArmada\FilamentJnt\FilamentJntServiceProvider;
use Mockery\MockInterface;
use Spatie\LaravelPackageTools\Package;

uses(FilamentJntTestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('configures the package name, config, and views', function (): void {
    /** @var Package&MockInterface $package */
    $package = Mockery::mock(Package::class);
    $package->shouldReceive('name')->once()->with('filament-jnt')->andReturnSelf();
    $package->shouldReceive('hasConfigFile')->once()->withNoArgs()->andReturnSelf();
    $package->shouldReceive('hasViews')->once()->withNoArgs()->andReturnSelf();

    $provider = new FilamentJntServiceProvider(app());
    $provider->configurePackage($package);
});

it('registers the FilamentJntPlugin singleton', function (): void {
    app()->register(FilamentJntServiceProvider::class);

    expect(app()->bound(FilamentJntPlugin::class))->toBeTrue();
    expect(app(FilamentJntPlugin::class))->toBeInstanceOf(FilamentJntPlugin::class);
});

