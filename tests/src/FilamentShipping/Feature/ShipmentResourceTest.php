<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Resources\ShipmentResource;
use AIArmada\Shipping\Models\Shipment;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;

uses(TestCase::class);

// ============================================
// ShipmentResource Tests
// ============================================

it('has correct navigation icon', function (): void {
    expect(ShipmentResource::getNavigationIcon())->toBe(Heroicon::OutlinedTruck);
});

it('has correct navigation group', function (): void {
    expect(ShipmentResource::getNavigationGroup())->toBe('Shipping');
});

it('uses shipment model', function (): void {
    expect(ShipmentResource::getModel())->toBe(Shipment::class);
});

it('has standard CRUD pages', function (): void {
    $pages = ShipmentResource::getPages();

    expect($pages)->toHaveKey('index');
    expect($pages)->toHaveKey('create');
    expect($pages)->toHaveKey('view');
    expect($pages)->toHaveKey('edit');
});

it('has relation managers configured', function (): void {
    $relations = ShipmentResource::getRelations();

    expect($relations)->toHaveCount(2);
});

it('builds shipment resource form schema', function (): void {
    $schema = ShipmentResource::form(Schema::make());

    expect($schema->getComponents())->not()->toBeEmpty();
});

it('builds shipment resource table definition', function (): void {
    $livewire = Mockery::mock(HasTable::class);

    $table = ShipmentResource::table(Table::make($livewire));

    expect($table->getColumns())->not()->toBeEmpty();
    expect($table->getRecordActions())->not()->toBeEmpty();
});
