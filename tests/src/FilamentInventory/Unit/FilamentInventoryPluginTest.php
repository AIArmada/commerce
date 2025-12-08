<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\FilamentInventory\FilamentInventoryPlugin;
use AIArmada\FilamentInventory\Resources\InventoryAllocationResource;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource;
use AIArmada\FilamentInventory\Resources\InventoryLevelResource;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource;
use AIArmada\FilamentInventory\Resources\InventoryMovementResource;
use AIArmada\FilamentInventory\Resources\InventorySerialResource;
use AIArmada\FilamentInventory\Widgets\InventoryStatsWidget;
use AIArmada\FilamentInventory\Widgets\LowInventoryAlertsWidget;
use Filament\Panel;
use Mockery;

uses(InventoryTestCase::class);

it('exposes a stable plugin id', function (): void {
    expect((new FilamentInventoryPlugin())->getId())->toBe('filament-inventory');
});

it('registers resources and widgets based on feature toggles', function (): void {
    config()->set('filament-inventory.features.batch_resource', false);
    config()->set('filament-inventory.features.serial_resource', false);
    config()->set('filament-inventory.features.expiring_batches_widget', false);
    config()->set('filament-inventory.features.reorder_suggestions_widget', false);
    config()->set('filament-inventory.features.backorders_widget', false);
    config()->set('filament-inventory.features.valuation_widget', false);
    config()->set('filament-inventory.features.kpi_widget', false);
    config()->set('filament-inventory.features.movement_trends_chart', false);
    config()->set('filament-inventory.features.abc_analysis_chart', false);

    /** @var Panel&\Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with([
            InventoryLocationResource::class,
            InventoryLevelResource::class,
            InventoryMovementResource::class,
            InventoryAllocationResource::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')
        ->once()
        ->with([
            InventoryStatsWidget::class,
            LowInventoryAlertsWidget::class,
        ])
        ->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentInventoryPlugin())->register($panel);
});

it('includes optional resources when toggles are enabled', function (): void {
    config()->set('filament-inventory.features.batch_resource', true);
    config()->set('filament-inventory.features.serial_resource', true);

    /** @var Panel&\Mockery\MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')
        ->once()
        ->with(Mockery::on(function (array $resources): bool {
            return in_array(InventoryBatchResource::class, $resources, true)
                && in_array(InventorySerialResource::class, $resources, true);
        }))
        ->andReturnSelf();

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentInventoryPlugin())->register($panel);
});
