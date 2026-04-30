<?php

declare(strict_types=1);

use AIArmada\FilamentInventory\Widgets\AbcAnalysisChart;
use AIArmada\FilamentInventory\Widgets\BackordersWidget;
use AIArmada\FilamentInventory\Widgets\ExpiringBatchesWidget;
use AIArmada\FilamentInventory\Widgets\InventoryKpiWidget;
use AIArmada\FilamentInventory\Widgets\InventoryStatsWidget;
use AIArmada\FilamentInventory\Widgets\InventoryValuationWidget;
use AIArmada\FilamentInventory\Widgets\LowInventoryAlertsWidget;
use AIArmada\FilamentInventory\Widgets\MovementTrendsChart;
use AIArmada\FilamentInventory\Widgets\ReorderSuggestionsWidget;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

beforeEach(function (): void {
    config()->set('inventory.owner.enabled', false);
    config()->set('filament-inventory.cache.stats_ttl', 0);
});

it('builds inventory stats widget stats', function (): void {
    $location = InventoryLocation::factory()->create(['is_active' => true]);

    InventoryLevel::create([
        'inventoryable_type' => 'Product',
        'inventoryable_id' => 'sku-1',
        'location_id' => $location->id,
        'quantity_on_hand' => 10,
        'quantity_reserved' => 2,
        'reorder_point' => 5,
    ]);

    $widget = new InventoryStatsWidget;

    expect(InventoryStatsWidget::canView())->toBeTrue();

    $method = new ReflectionMethod($widget, 'getStats');

    /** @var array $stats */
    $stats = $method->invoke($widget);

    expect($stats)->not()->toBeEmpty();
});

it('builds low inventory alerts widget table', function (): void {
    $location = InventoryLocation::factory()->create(['is_active' => true]);

    InventoryLevel::create([
        'inventoryable_type' => 'Product',
        'inventoryable_id' => 'sku-low',
        'location_id' => $location->id,
        'quantity_on_hand' => 2,
        'quantity_reserved' => 0,
        'reorder_point' => 5,
    ]);

    $widget = new LowInventoryAlertsWidget;

    $livewire = Mockery::mock(HasTable::class);
    $table = $widget->table(Table::make($livewire));

    expect(LowInventoryAlertsWidget::canView())->toBeTrue();
    expect($table->getColumns())->not()->toBeEmpty();
});

it('builds movement trends chart data', function (): void {
    $location = InventoryLocation::factory()->create(['is_active' => true]);

    InventoryMovement::create([
        'inventoryable_type' => 'Product',
        'inventoryable_id' => 'sku-1',
        'from_location_id' => null,
        'to_location_id' => $location->id,
        'quantity' => 5,
        'type' => MovementType::Receipt->value,
        'occurred_at' => now()->subDays(2),
    ]);

    $widget = new MovementTrendsChart;

    $method = new ReflectionMethod($widget, 'getData');

    /** @var array $data */
    $data = $method->invoke($widget);

    expect(MovementTrendsChart::canView())->toBeTrue();
    expect($data)->toHaveKey('datasets');
    expect($data)->toHaveKey('labels');
});

it('builds abc analysis chart description and data without crashing', function (): void {
    $widget = new AbcAnalysisChart;

    $description = $widget->getDescription();

    $method = new ReflectionMethod($widget, 'getData');

    /** @var array $data */
    $data = $method->invoke($widget);

    expect(AbcAnalysisChart::canView())->toBeTrue();
    expect($description)->toBeString();
    expect($data)->toHaveKey('datasets');
    expect($data)->toHaveKey('labels');
});

it('builds tables for optional table widgets without evaluating routes', function (): void {
    $livewire = Mockery::mock(HasTable::class);

    $backorders = (new BackordersWidget)->table(Table::make($livewire));
    expect($backorders->getColumns())->not()->toBeEmpty();

    $reorder = (new ReorderSuggestionsWidget)->table(Table::make($livewire));
    expect($reorder->getColumns())->not()->toBeEmpty();

    $expiring = (new ExpiringBatchesWidget)->table(Table::make($livewire));
    expect($expiring->getColumns())->not()->toBeEmpty();
});

it('builds kpi and valuation widgets stats', function (): void {
    $kpi = new InventoryKpiWidget;
    $valuation = new InventoryValuationWidget;

    $kpiMethod = new ReflectionMethod($kpi, 'getStats');

    /** @var array $kpiStats */
    $kpiStats = $kpiMethod->invoke($kpi);

    $valuationMethod = new ReflectionMethod($valuation, 'getStats');

    /** @var array $valuationStats */
    $valuationStats = $valuationMethod->invoke($valuation);

    expect($kpiStats)->not()->toBeEmpty();
    expect($valuationStats)->not()->toBeEmpty();
});
