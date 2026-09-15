<?php

declare(strict_types=1);

use AIArmada\FilamentTicketing\RelationManagers\TicketTypesRelationManager;
use AIArmada\FilamentTicketing\Resources\TicketTypeResource;
use AIArmada\FilamentTicketing\Schemas\TicketTypeFormSchema;
use AIArmada\FilamentTicketing\Tables\TicketTypeTable;
use AIArmada\FilamentTicketing\Tests\Fixtures\OwnedTicketable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\MorphToSelect;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component as LivewireComponent;

if (! function_exists('filamentTicketingRm_makeSchemaLivewire')) {
    function filamentTicketingRm_makeSchemaLivewire(): LivewireComponent & HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            use InteractsWithSchemas;

            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(
                string $key,
                bool $withHidden = false,
                array $skipComponentsChildContainersWhileSearching = [],
            ): Component | Action | ActionGroup | null {
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

if (! function_exists('filamentTicketingRm_fieldNames')) {
    function filamentTicketingRm_fieldNames(array $components): array
    {
        $names = [];

        foreach ($components as $component) {
            if (! is_object($component)) {
                continue;
            }

            if (method_exists($component, 'getName')) {
                $names[] = $component->getName();
            }

            if ($component instanceof MorphToSelect) {
                continue;
            }

            if (method_exists($component, 'getChildComponents')) {
                $names = [...$names, ...filamentTicketingRm_fieldNames($component->getChildComponents())];
            }
        }

        return $names;
    }
}

afterEach(function (): void {
    if (class_exists(Mockery::class)) {
        Mockery::close();
    }
});

it('manages ticket types on any ticketTypes relationship', function (): void {
    $relationship = new ReflectionProperty(TicketTypesRelationManager::class, 'relationship');

    expect(is_subclass_of(TicketTypesRelationManager::class, RelationManager::class))->toBeTrue()
        ->and($relationship->getValue())->toBe('ticketTypes');
});

it('includes the ticketable picker only outside a fixed owner', function (): void {
    $livewire = filamentTicketingRm_makeSchemaLivewire();

    $standalone = filamentTicketingRm_fieldNames(
        Schema::make($livewire)->schema(TicketTypeFormSchema::make())->getComponents()
    );
    $scoped = filamentTicketingRm_fieldNames(
        Schema::make($livewire)->schema(TicketTypeFormSchema::make(new OwnedTicketable))->getComponents()
    );

    expect($standalone)->toContain('ticketable')
        ->and($scoped)->not->toContain('ticketable')
        ->and($scoped)->toContain('name', 'code', 'price', 'status', 'visibility');
});

it('shares the ticket type table between the resource and relation managers', function (): void {
    $table = Table::make(Mockery::mock(HasTable::class));

    $sharedColumns = array_map(
        static fn (object $column): string => $column->getName(),
        TicketTypeTable::columns()
    );
    $sharedFilters = array_map(
        static fn (object $filter): string => $filter->getName(),
        TicketTypeTable::filters()
    );
    $resourceColumns = array_values(array_map(
        static fn (object $column): string => $column->getName(),
        TicketTypeResource::table($table)->getColumns()
    ));

    expect($sharedColumns)->toBe(['name', 'code', 'access_type', 'price', 'status', 'visibility', 'sales_starts_at', 'sales_ends_at', 'created_at'])
        ->and($sharedFilters)->toBe(['status', 'access_type', 'visibility'])
        ->and($resourceColumns)->toBe($sharedColumns);
});
