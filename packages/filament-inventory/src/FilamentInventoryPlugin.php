<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory;

use AIArmada\FilamentInventory\Resources\InventoryAllocationResource;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource;
use AIArmada\FilamentInventory\Resources\InventoryLevelResource;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource;
use AIArmada\FilamentInventory\Resources\InventoryMovementResource;
use AIArmada\FilamentInventory\Resources\InventorySerialResource;
use AIArmada\FilamentInventory\Widgets\AbcAnalysisChart;
use AIArmada\FilamentInventory\Widgets\BackordersWidget;
use AIArmada\FilamentInventory\Widgets\ExpiringBatchesWidget;
use AIArmada\FilamentInventory\Widgets\InventoryKpiWidget;
use AIArmada\FilamentInventory\Widgets\InventoryStatsWidget;
use AIArmada\FilamentInventory\Widgets\InventoryValuationWidget;
use AIArmada\FilamentInventory\Widgets\LowInventoryAlertsWidget;
use AIArmada\FilamentInventory\Widgets\MovementTrendsChart;
use AIArmada\FilamentInventory\Widgets\ReorderSuggestionsWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentInventoryPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-inventory';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources($this->getResources())
            ->widgets($this->getWidgets());
    }

    public function boot(Panel $panel): void
    {
        // No-op: the service provider handles runtime integration hooks.
    }

    /**
     * @return array<class-string>
     */
    protected function getResources(): array
    {
        $resources = [
            InventoryLocationResource::class,
            InventoryLevelResource::class,
            InventoryMovementResource::class,
            InventoryAllocationResource::class,
        ];

        if (config('filament-inventory.features.batch_resource', true)) {
            $resources[] = InventoryBatchResource::class;
        }

        if (config('filament-inventory.features.serial_resource', true)) {
            $resources[] = InventorySerialResource::class;
        }

        return $resources;
    }

    /**
     * @return array<class-string>
     */
    protected function getWidgets(): array
    {
        $widgets = [
            InventoryStatsWidget::class,
            LowInventoryAlertsWidget::class,
        ];

        if (config('filament-inventory.features.expiring_batches_widget', true)) {
            $widgets[] = ExpiringBatchesWidget::class;
        }

        if (config('filament-inventory.features.reorder_suggestions_widget', true)) {
            $widgets[] = ReorderSuggestionsWidget::class;
        }

        if (config('filament-inventory.features.backorders_widget', true)) {
            $widgets[] = BackordersWidget::class;
        }

        if (config('filament-inventory.features.valuation_widget', true)) {
            $widgets[] = InventoryValuationWidget::class;
        }

        if (config('filament-inventory.features.kpi_widget', true)) {
            $widgets[] = InventoryKpiWidget::class;
        }

        if (config('filament-inventory.features.movement_trends_chart', true)) {
            $widgets[] = MovementTrendsChart::class;
        }

        if (config('filament-inventory.features.abc_analysis_chart', true)) {
            $widgets[] = AbcAnalysisChart::class;
        }

        return $widgets;
    }
}
