<?php

declare(strict_types=1);

use AIArmada\FilamentCustomers\Resources\CustomerResource;
use AIArmada\FilamentCustomers\Resources\SegmentResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component as LivewireComponent;

if (! function_exists('filamentCustomers_makeSchemaLivewire')) {
    function filamentCustomers_makeSchemaLivewire(): LivewireComponent & HasSchemas
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

it('builds CustomerResource form/table/infolist schemas', function (): void {
    $schema = CustomerResource::form(Schema::make(filamentCustomers_makeSchemaLivewire()));
    expect($schema->getComponents())->not()->toBeEmpty();

    $infolist = CustomerResource::infolist(Schema::make(filamentCustomers_makeSchemaLivewire()));
    expect($infolist->getComponents())->not()->toBeEmpty();

    $livewire = Mockery::mock(HasTable::class);
    $table = CustomerResource::table(Table::make($livewire));

    expect($table->getColumns())->not()->toBeEmpty();
    expect($table->getRecordActions())->not()->toBeEmpty();
});

it('builds SegmentResource form/table schemas', function (): void {
    $schema = SegmentResource::form(Schema::make(filamentCustomers_makeSchemaLivewire()));
    expect($schema->getComponents())->not()->toBeEmpty();

    $livewire = Mockery::mock(HasTable::class);
    $table = SegmentResource::table(Table::make($livewire));

    expect($table->getColumns())->not()->toBeEmpty();
    expect($table->getRecordActions())->not()->toBeEmpty();
});
