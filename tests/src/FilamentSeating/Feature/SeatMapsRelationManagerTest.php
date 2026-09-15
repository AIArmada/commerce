<?php

declare(strict_types=1);

use AIArmada\FilamentSeating\RelationManagers\SeatMapsRelationManager;
use AIArmada\FilamentSeating\Resources\SeatMapResource;
use AIArmada\FilamentSeating\Schemas\SeatMapFormSchema;
use AIArmada\FilamentSeating\Tables\SeatMapTable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component as LivewireComponent;

if (! function_exists('filamentSeatingRm_makeSchemaLivewire')) {
    function filamentSeatingRm_makeSchemaLivewire(): LivewireComponent & HasSchemas
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

if (! function_exists('filamentSeatingRm_fieldNames')) {
    function filamentSeatingRm_fieldNames(array $components): array
    {
        $names = [];

        foreach ($components as $component) {
            if (! is_object($component)) {
                continue;
            }

            if (method_exists($component, 'getName')) {
                $names[] = $component->getName();
            }

            if (method_exists($component, 'getChildComponents')) {
                $names = [...$names, ...filamentSeatingRm_fieldNames($component->getChildComponents())];
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

it('manages seat maps on any seatMaps relationship', function (): void {
    $relationship = new ReflectionProperty(SeatMapsRelationManager::class, 'relationship');

    expect(is_subclass_of(SeatMapsRelationManager::class, RelationManager::class))->toBeTrue()
        ->and($relationship->getValue())->toBe('seatMaps');
});

it('shares the seat map form between the resource and relation managers', function (): void {
    $livewire = filamentSeatingRm_makeSchemaLivewire();
    $shared = filamentSeatingRm_fieldNames(
        Schema::make($livewire)->schema(SeatMapFormSchema::make())->getComponents()
    );
    $resource = filamentSeatingRm_fieldNames(
        SeatMapResource::form(Schema::make($livewire))->getComponents()
    );

    foreach (['name', 'slug', 'version', 'status', 'layout_metadata'] as $field) {
        expect($shared)->toContain($field)
            ->and($resource)->toContain($field);
    }
});

it('shares the seat map table between the resource and relation managers', function (): void {
    $table = Table::make(Mockery::mock(HasTable::class));

    $sharedColumns = array_map(
        static fn (object $column): string => $column->getName(),
        SeatMapTable::columns()
    );
    $sharedFilters = array_map(
        static fn (object $filter): string => $filter->getName(),
        SeatMapTable::filters()
    );
    $resourceColumns = array_values(array_map(
        static fn (object $column): string => $column->getName(),
        SeatMapResource::table($table)->getColumns()
    ));

    expect($sharedColumns)->toBe(['name', 'slug', 'version', 'sections_count', 'status', 'created_at'])
        ->and($sharedFilters)->toBe(['status'])
        ->and($resourceColumns)->toBe($sharedColumns);
});
