<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Resources\ReturnAuthorizationResource\RelationManagers\ItemsRelationManager;
use AIArmada\FilamentShipping\Resources\ShipmentResource\RelationManagers\EventsRelationManager;
use AIArmada\FilamentShipping\Resources\ShipmentResource\RelationManagers\ItemsRelationManager as ShipmentItemsRelationManager;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource\RelationManagers\RatesRelationManager;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Support\Contracts\TranslatableContentDriver;
use Livewire\Component as LivewireComponent;

if (! function_exists('filamentShipping_makeSchemaLivewire')) {
    function filamentShipping_makeSchemaLivewire(): LivewireComponent & HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(string $key, bool $withHidden = false, array $skipComponentsChildContainersWhileSearching = []): Filament\Schemas\Components\Component | Filament\Actions\Action | Filament\Actions\ActionGroup | null
            {
                return null;
            }

            public function getSchema(string $name): ?Schema
            {
                return null;
            }

            public function currentlyValidatingSchema(?Schema $schema): void {}

            public function getDefaultTestingSchemaName(): ?string
            {
                return null;
            }
        };
    }
}

uses(TestCase::class);

// ============================================
// Relation Managers Tests
// ============================================

describe('RatesRelationManager', function (): void {
    it('can be instantiated', function (): void {
        $manager = new RatesRelationManager;

        expect($manager)->toBeInstanceOf(RatesRelationManager::class);
    });

    it('has correct relationship name', function (): void {
        $reflection = new ReflectionProperty(RatesRelationManager::class, 'relationship');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('rates');
    });

    it('has correct record title attribute', function (): void {
        $reflection = new ReflectionProperty(RatesRelationManager::class, 'recordTitleAttribute');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('name');
    });

    it('builds rates relation manager form schema', function (): void {
        $manager = new RatesRelationManager;

        $schema = $manager->form(Schema::make(filamentShipping_makeSchemaLivewire()));

        expect($schema->getComponents())->not()->toBeEmpty();
    });

    it('builds rates relation manager table definition', function (): void {
        $manager = new RatesRelationManager;

        $livewire = Mockery::mock(HasTable::class);
        $table = $manager->table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
        expect($table->getRecordActions())->not()->toBeEmpty();
    });
});

describe('ReturnAuthorizationItemsRelationManager', function (): void {
    it('can be instantiated', function (): void {
        $manager = new ItemsRelationManager;

        expect($manager)->toBeInstanceOf(ItemsRelationManager::class);
    });

    it('has correct relationship name', function (): void {
        $reflection = new ReflectionProperty(ItemsRelationManager::class, 'relationship');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('items');
    });

    it('has correct record title attribute', function (): void {
        $reflection = new ReflectionProperty(ItemsRelationManager::class, 'recordTitleAttribute');
        $reflection->setAccessible(true);

        expect($reflection->getValue(null))->toBe('name');
    });

    it('builds items relation manager table definition', function (): void {
        $manager = new ItemsRelationManager;

        $livewire = Mockery::mock(HasTable::class);
        $table = $manager->table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
    });
});

describe('ShipmentResource relation managers', function (): void {
    it('builds shipment items relation manager table definition', function (): void {
        $manager = new ShipmentItemsRelationManager;

        $livewire = Mockery::mock(HasTable::class);
        $table = $manager->table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
        expect($table->getRecordActions())->not()->toBeEmpty();
    });

    it('builds shipment events relation manager table definition', function (): void {
        $manager = new EventsRelationManager;

        $livewire = Mockery::mock(HasTable::class);
        $table = $manager->table(Table::make($livewire));

        expect($table->getColumns())->not()->toBeEmpty();
    });
});
