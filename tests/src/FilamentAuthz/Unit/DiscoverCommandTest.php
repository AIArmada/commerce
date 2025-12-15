<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Console\DiscoverCommand;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredPage;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredResource;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredWidget;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('permission.models.permission', Permission::class);
});

describe('DiscoverCommand', function () {
    it('has correct signature', function () {
        $command = new DiscoverCommand;
        $signature = $command->getName();

        expect($signature)->toBe('authz:discover');
    });

    it('discovers all entity types by default', function () {
        $discovery = Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->andReturn(collect());
        $discovery->shouldReceive('discoverPages')->andReturn(collect());
        $discovery->shouldReceive('discoverWidgets')->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $discovery);

        $this->artisan('authz:discover')
            ->assertSuccessful();
    });

    it('discovers only resources when type option is resources', function () {
        $discovery = Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->once()->andReturn(collect());
        $discovery->shouldNotReceive('discoverPages');
        $discovery->shouldNotReceive('discoverWidgets');

        $this->app->instance(EntityDiscoveryService::class, $discovery);

        $this->artisan('authz:discover', ['--type' => 'resources'])
            ->assertSuccessful();
    });

    it('discovers only pages when type option is pages', function () {
        $discovery = Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldNotReceive('discoverResources');
        $discovery->shouldReceive('discoverPages')->once()->andReturn(collect());
        $discovery->shouldNotReceive('discoverWidgets');

        $this->app->instance(EntityDiscoveryService::class, $discovery);

        $this->artisan('authz:discover', ['--type' => 'pages'])
            ->assertSuccessful();
    });

    it('discovers only widgets when type option is widgets', function () {
        $discovery = Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldNotReceive('discoverResources');
        $discovery->shouldNotReceive('discoverPages');
        $discovery->shouldReceive('discoverWidgets')->once()->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $discovery);

        $this->artisan('authz:discover', ['--type' => 'widgets'])
            ->assertSuccessful();
    });

    it('filters by panel when option provided', function () {
        $discovery = Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')
            ->with(Mockery::on(fn ($opts) => $opts['panels'] === ['admin']))
            ->andReturn(collect());
        $discovery->shouldReceive('discoverPages')
            ->with(Mockery::on(fn ($opts) => $opts['panels'] === ['admin']))
            ->andReturn(collect());
        $discovery->shouldReceive('discoverWidgets')
            ->with(Mockery::on(fn ($opts) => $opts['panels'] === ['admin']))
            ->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $discovery);

        $this->artisan('authz:discover', ['--panel' => 'admin'])
            ->assertSuccessful();
    });

    it('outputs json format when format option is json', function () {
        $discovery = Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->andReturn(collect());
        $discovery->shouldReceive('discoverPages')->andReturn(collect());
        $discovery->shouldReceive('discoverWidgets')->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $discovery);

        $this->artisan('authz:discover', ['--format' => 'json'])
            ->assertSuccessful();
    });

    it('displays discovered resources in table', function () {
        $resource = new DiscoveredResource(
            fqcn: 'App\Filament\Resources\UserResource',
            model: 'App\Models\User',
            permissions: ['user.view', 'user.create', 'user.update', 'user.delete'],
            metadata: [],
            panel: 'admin'
        );

        $discovery = Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->andReturn(collect([$resource]));
        $discovery->shouldReceive('discoverPages')->andReturn(collect());
        $discovery->shouldReceive('discoverWidgets')->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $discovery);

        $this->artisan('authz:discover')
            ->expectsOutputToContain('Resources')
            ->assertSuccessful();
    });

    it('displays discovered pages in table', function () {
        $page = new DiscoveredPage(
            fqcn: 'App\Filament\Pages\SettingsPage',
            title: 'Settings',
            panel: 'admin',
            permissions: ['page.settings'],
            metadata: []
        );

        $discovery = Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->andReturn(collect());
        $discovery->shouldReceive('discoverPages')->andReturn(collect([$page]));
        $discovery->shouldReceive('discoverWidgets')->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $discovery);

        $this->artisan('authz:discover')
            ->expectsOutputToContain('Pages')
            ->assertSuccessful();
    });

    it('displays discovered widgets in table', function () {
        $widget = new DiscoveredWidget(
            fqcn: 'App\Filament\Widgets\StatsWidget',
            type: 'stats',
            panel: 'admin',
            permissions: ['widget.stats'],
            metadata: []
        );

        $discovery = Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->andReturn(collect());
        $discovery->shouldReceive('discoverPages')->andReturn(collect());
        $discovery->shouldReceive('discoverWidgets')->andReturn(collect([$widget]));

        $this->app->instance(EntityDiscoveryService::class, $discovery);

        $this->artisan('authz:discover')
            ->expectsOutputToContain('Widgets')
            ->assertSuccessful();
    });

    it('generates permissions when generate option is provided', function () {
        $resource = new DiscoveredResource(
            fqcn: 'App\Filament\Resources\UserResource',
            model: 'App\Models\User',
            permissions: ['view', 'create'],
            metadata: [],
            panel: 'admin'
        );

        $page = new DiscoveredPage(
            fqcn: 'App\Filament\Pages\SettingsPage',
            title: 'Settings',
            panel: 'admin',
            permissions: ['page.settings'],
            metadata: []
        );

        $widget = new DiscoveredWidget(
            fqcn: 'App\Filament\Widgets\StatsWidget',
            type: 'stats',
            panel: 'admin',
            permissions: ['widget.stats'],
            metadata: []
        );

        $discovery = Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->andReturn(collect([$resource]));
        $discovery->shouldReceive('discoverPages')->andReturn(collect([$page]));
        $discovery->shouldReceive('discoverWidgets')->andReturn(collect([$widget]));

        $this->app->instance(EntityDiscoveryService::class, $discovery);

        // Just verify the command runs and generates some permissions
        $this->artisan('authz:discover', ['--generate' => true])
            ->expectsOutputToContain('Generating permissions')
            ->assertSuccessful();

        // At least some permissions should have been created
        expect(Permission::count())->toBeGreaterThan(0);
    });

    it('shows none found when no entities discovered', function () {
        $discovery = Mockery::mock(EntityDiscoveryService::class);
        $discovery->shouldReceive('discoverResources')->andReturn(collect());
        $discovery->shouldReceive('discoverPages')->andReturn(collect());
        $discovery->shouldReceive('discoverWidgets')->andReturn(collect());

        $this->app->instance(EntityDiscoveryService::class, $discovery);

        $this->artisan('authz:discover')
            ->expectsOutputToContain('None found')
            ->assertSuccessful();
    });
});
