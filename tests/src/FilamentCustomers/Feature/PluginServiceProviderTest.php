<?php

declare(strict_types=1);

use AIArmada\FilamentCustomers\FilamentCustomersPlugin;
use AIArmada\FilamentCustomers\FilamentCustomersServiceProvider;
use AIArmada\FilamentCustomers\Resources\CustomerResource;
use AIArmada\FilamentCustomers\Resources\SegmentResource;
use AIArmada\FilamentCustomers\Widgets\CustomerStatsWidget;
use AIArmada\FilamentCustomers\Widgets\RecentCustomersWidget;
use Filament\Panel;
use Illuminate\Support\ServiceProvider;

it('FilamentCustomersPlugin registers resources and widgets', function (): void {
    config()->set('filament-customers.features.merge_customers', false);

    $plugin = FilamentCustomersPlugin::make();

    expect($plugin->getId())->toBe('filament-customers');

    $panel = Mockery::mock(Panel::class);

    $panel
        ->shouldReceive('resources')
        ->once()
        ->with([
            CustomerResource::class,
            SegmentResource::class,
        ])
        ->andReturnSelf();

    $panel
        ->shouldReceive('pages')
        ->once()
        ->with([])
        ->andReturnSelf();

    $panel
        ->shouldReceive('widgets')
        ->once()
        ->with([
            CustomerStatsWidget::class,
            RecentCustomersWidget::class,
        ])
        ->andReturnSelf();

    $plugin->register($panel);
    $plugin->boot($panel);
});

it('FilamentCustomersServiceProvider publishes views', function (): void {
    $this->app->register(FilamentCustomersServiceProvider::class);

    $providerFile = (new ReflectionClass(FilamentCustomersServiceProvider::class))->getFileName();
    $sourceRaw = dirname($providerFile) . '/../resources/views';
    $sourceReal = realpath($sourceRaw);

    expect($sourceReal)->not->toBeFalse();

    $paths = ServiceProvider::pathsToPublish(FilamentCustomersServiceProvider::class, 'filament-customers-views');

    $matchedKey = collect(array_keys($paths))
        ->first(fn (string $path): bool => realpath($path) === $sourceReal);

    expect($matchedKey)->not->toBeNull();
    expect($paths[$matchedKey])->toBe(resource_path('views/vendor/filament-customers'));
});

it('FilamentCustomers config defines plugin feature toggles', function (): void {
    $config = require __DIR__ . '/../../../../packages/filament-customers/config/filament-customers.php';

    expect($config['features'])->toHaveKey('merge_customers', true)
        ->and($config['features'])->toHaveKey('segment_rebuild', false)
        ->and($config['features'])->toHaveKey('address_validation', false);
});
