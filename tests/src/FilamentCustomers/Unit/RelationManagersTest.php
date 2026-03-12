<?php

declare(strict_types=1);

use AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers\AddressesRelationManager;
use AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers\NotesRelationManager;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
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

it('builds relation manager schemas', function (): void {
    $livewire = filamentCustomers_makeSchemaLivewire();

    foreach ([AddressesRelationManager::class, NotesRelationManager::class] as $managerClass) {
        /** @var RelationManager $manager */
        $manager = new $managerClass($livewire);

        $schema = $manager->form(Schema::make($livewire));
        expect($schema->getComponents())->not()->toBeEmpty();

        $tableLivewire = Mockery::mock(HasTable::class);
        $table = $manager->table(Table::make($tableLivewire));
        expect($table->getColumns())->not()->toBeEmpty();
    }
});
