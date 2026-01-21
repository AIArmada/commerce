<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentDocs\FilamentDocsPlugin;
use AIArmada\FilamentDocs\FilamentDocsServiceProvider;
use Mockery\MockInterface;
use Spatie\LaravelPackageTools\Package;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('configures the package name, config, and routes', function (): void {
    /** @var Package&MockInterface $package */
    $package = Mockery::mock(Package::class);
    $package->shouldReceive('name')->once()->with('filament-docs')->andReturnSelf();
    $package->shouldReceive('hasViews')->once()->andReturnSelf();
    $package->shouldReceive('hasConfigFile')->once()->with('filament-docs')->andReturnSelf();
    $package->shouldReceive('hasRoute')->once()->with('filament-docs')->andReturnSelf();

    $provider = new FilamentDocsServiceProvider(app());
    $provider->configurePackage($package);
});

it('registers and provides the FilamentDocsPlugin singleton', function (): void {
    app()->register(FilamentDocsServiceProvider::class);

    expect(app()->bound(FilamentDocsPlugin::class))->toBeTrue();
    expect((new FilamentDocsServiceProvider(app()))->provides())->toContain(FilamentDocsPlugin::class);
    expect(app(FilamentDocsPlugin::class))->toBeInstanceOf(FilamentDocsPlugin::class);
});
