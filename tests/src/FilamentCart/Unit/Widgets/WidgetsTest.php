<?php

declare(strict_types=1);

use AIArmada\FilamentCart\FilamentCartPlugin;
use AIArmada\FilamentCart\Widgets\AbandonedCartsWidget;
use AIArmada\FilamentCart\Widgets\CartStatsOverviewWidget;
use AIArmada\FilamentCart\Widgets\CartStatsWidget;
use AIArmada\FilamentCart\Widgets\LiveStatsWidget;
use AIArmada\FilamentCart\Widgets\RecentActivityWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\Widget;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

describe('Widgets Instantiation', function (): void {
    it('can instantiate AbandonedCartsWidget', function (): void {
        $widget = new AbandonedCartsWidget;
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('can instantiate CartStatsOverviewWidget', function (): void {
        $widget = new CartStatsOverviewWidget;
        expect($widget)->toBeInstanceOf(StatsOverviewWidget::class);
    });

    it('can instantiate CartStatsWidget', function (): void {
        $widget = new CartStatsWidget;
        expect($widget)->toBeInstanceOf(StatsOverviewWidget::class);
    });

    it('can instantiate LiveStatsWidget', function (): void {
        $widget = new LiveStatsWidget;
        expect($widget)->toBeInstanceOf(StatsOverviewWidget::class);
    });

    it('can instantiate RecentActivityWidget', function (): void {
        $widget = new RecentActivityWidget;
        expect($widget)->toBeInstanceOf(Widget::class);
    });

    it('respects widget feature toggles in plugin registration', function (): void {
        config()->set('filament-cart.widgets.stats_overview', false);
        config()->set('filament-cart.widgets.abandoned_carts', true);
        config()->set('filament-cart.features.monitoring', false);

        $plugin = FilamentCartPlugin::make();
        $method = new ReflectionMethod($plugin, 'getWidgets');
        $method->setAccessible(true);

        /** @var array<class-string> $widgets */
        $widgets = $method->invoke($plugin);

        expect($widgets)
            ->toContain(AbandonedCartsWidget::class)
            ->not->toContain(CartStatsWidget::class)
            ->not->toContain(LiveStatsWidget::class)
            ->not->toContain(RecentActivityWidget::class);
    });

    it('casts metadata to text on PostgreSQL for abandoned cart email search', function (): void {
        $widget = new AbandonedCartsWidget;
        $method = new ReflectionMethod($widget, 'applyMetadataSearch');
        $method->setAccessible(true);

        $builder = Mockery::mock(Builder::class);
        $model = Mockery::mock(Model::class);
        $connection = Mockery::mock(Connection::class);

        $builder->shouldReceive('getModel')->once()->andReturn($model);
        $model->shouldReceive('getConnection')->once()->andReturn($connection);
        $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');
        $builder->shouldReceive('whereRaw')
            ->once()
            ->with('CAST(metadata AS TEXT) ILIKE ?', ['%alice%'])
            ->andReturnSelf();

        $method->invoke($widget, $builder, 'alice');
    });

    it('uses like search on non-PostgreSQL drivers for abandoned cart email search', function (): void {
        $widget = new AbandonedCartsWidget;
        $method = new ReflectionMethod($widget, 'applyMetadataSearch');
        $method->setAccessible(true);

        $builder = Mockery::mock(Builder::class);
        $model = Mockery::mock(Model::class);
        $connection = Mockery::mock(Connection::class);

        $builder->shouldReceive('getModel')->once()->andReturn($model);
        $model->shouldReceive('getConnection')->once()->andReturn($connection);
        $connection->shouldReceive('getDriverName')->once()->andReturn('sqlite');
        $builder->shouldReceive('where')
            ->once()
            ->with('metadata', 'like', '%alice%')
            ->andReturnSelf();

        $method->invoke($widget, $builder, 'alice');
    });
});
