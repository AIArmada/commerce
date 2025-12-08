<?php

declare(strict_types=1);

use AIArmada\FilamentStock\FilamentStockPlugin;
use AIArmada\FilamentStock\Resources\StockReservationResource;
use AIArmada\FilamentStock\Resources\StockTransactionResource;
use AIArmada\FilamentStock\Widgets\StockStatsWidget;
use Filament\Panel;
use Mockery;

it('exposes a stable plugin id', function (): void {
    expect((new FilamentStockPlugin())->getId())->toBe('filament-stock');
});

it('registers stock resources and widgets', function (): void {
    /** @var Panel&\Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            StockTransactionResource::class,
            StockReservationResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([StockStatsWidget::class])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentStockPlugin())->register($panel);
});
