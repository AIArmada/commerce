<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentProducts\FilamentProductsPlugin;
use AIArmada\FilamentProducts\Pages\BulkEditProducts;
use AIArmada\FilamentProducts\Pages\ImportExportProducts;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource;
use AIArmada\FilamentProducts\Resources\AttributeResource;
use AIArmada\FilamentProducts\Resources\AttributeSetResource;
use AIArmada\FilamentProducts\Resources\CategoryResource;
use AIArmada\FilamentProducts\Resources\CollectionResource;
use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\FilamentProducts\Resources\ProductResource\RelationManagers\OptionsRelationManager;
use AIArmada\FilamentProducts\Resources\ProductResource\RelationManagers\VariantsRelationManager;
use AIArmada\FilamentProducts\Widgets\CategoryDistributionChart;
use AIArmada\FilamentProducts\Widgets\LowStockAlertWidget;
use AIArmada\FilamentProducts\Widgets\ProductStatsWidget;
use AIArmada\FilamentProducts\Widgets\TopSellingProductsWidget;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

function makeTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

it('builds resource schemas and tables', function (): void {
    foreach ([
        ProductResource::class,
        CategoryResource::class,
        CollectionResource::class,
        AttributeResource::class,
        AttributeGroupResource::class,
        AttributeSetResource::class,
    ] as $resource) {
        expect($resource::form(Schema::make()))->toBeInstanceOf(Schema::class);
        expect($resource::table(makeTable()))->toBeInstanceOf(Table::class);
    }
});

it('builds relation managers', function (): void {
    $options = app(OptionsRelationManager::class);
    $variants = app(VariantsRelationManager::class);

    expect($options->table(makeTable()))->toBeInstanceOf(Table::class);
    expect($variants->table(makeTable()))->toBeInstanceOf(Table::class);
});

it('builds standalone pages', function (): void {
    $bulk = app(BulkEditProducts::class);
    $importExport = app(ImportExportProducts::class);

    expect($bulk)->toBeInstanceOf(BulkEditProducts::class);
    expect($importExport)->toBeInstanceOf(ImportExportProducts::class);
});

it('builds widgets', function (): void {
    foreach ([
        ProductStatsWidget::class,
        LowStockAlertWidget::class,
        CategoryDistributionChart::class,
        TopSellingProductsWidget::class,
    ] as $widget) {
        expect(app($widget))->toBeInstanceOf($widget);
    }
});

it('registers the filament products plugin', function (): void {
    $panel = Panel::make()->id('admin');

    $plugin = FilamentProductsPlugin::make();

    expect($plugin->getId())->toBe('filament-products');

    $plugin->register($panel);
    $plugin->boot($panel);
});
