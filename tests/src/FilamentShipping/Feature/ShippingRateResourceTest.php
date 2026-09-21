<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Resources\ShippingRateResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
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
// ShippingRateResource Tests
// ============================================

it('has standard CRUD pages', function (): void {
    $pages = ShippingRateResource::getPages();

    expect($pages)->toHaveKey('index');
    expect($pages)->toHaveKey('create');
    expect($pages)->toHaveKey('edit');
});

it('builds shipping rate resource form schema', function (): void {
    $schema = ShippingRateResource::form(Schema::make(filamentShipping_makeSchemaLivewire()));

    expect($schema->getComponents())->not()->toBeEmpty();
});

it('builds shipping rate resource table definition', function (): void {
    $livewire = Mockery::mock(HasTable::class);

    $table = ShippingRateResource::table(Table::make($livewire));

    expect($table->getColumns())->not()->toBeEmpty();
    expect($table->getRecordActions())->not()->toBeEmpty();
});
