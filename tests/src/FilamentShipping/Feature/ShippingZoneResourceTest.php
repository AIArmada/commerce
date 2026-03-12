<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Resources\ShippingZoneResource;
use AIArmada\Shipping\Models\ShippingZone;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
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

            public function getSchemaComponent(string $key, bool $withHidden = false, array $skipComponentsChildContainersWhileSearching = []): Component | Action | ActionGroup | null
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
// ShippingZoneResource Tests
// ============================================

it('has correct navigation icon', function (): void {
    expect(ShippingZoneResource::getNavigationIcon())->toBe(Heroicon::OutlinedMap);
});

it('has correct navigation group', function (): void {
    expect(ShippingZoneResource::getNavigationGroup())->toBe('Shipping');
});

it('uses shipping zone model', function (): void {
    expect(ShippingZoneResource::getModel())->toBe(ShippingZone::class);
});

it('has standard CRUD pages', function (): void {
    $pages = ShippingZoneResource::getPages();

    expect($pages)->toHaveKey('index');
    expect($pages)->toHaveKey('create');
    expect($pages)->toHaveKey('edit');
});

it('builds shipping zone resource form schema', function (): void {
    $schema = ShippingZoneResource::form(Schema::make(filamentShipping_makeSchemaLivewire()));

    expect($schema->getComponents())->not()->toBeEmpty();
});

it('builds shipping zone resource table definition', function (): void {
    $livewire = Mockery::mock(HasTable::class);

    $table = ShippingZoneResource::table(Table::make($livewire));

    expect($table->getColumns())->not()->toBeEmpty();
    expect($table->getRecordActions())->not()->toBeEmpty();
});
