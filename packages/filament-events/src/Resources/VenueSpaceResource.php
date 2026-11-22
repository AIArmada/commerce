<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\Events\Models\VenueSpace;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\FilamentEvents\Resources\VenueSpaceResource\Pages\CreateVenueSpace;
use AIArmada\FilamentEvents\Resources\VenueSpaceResource\Pages\EditVenueSpace;
use AIArmada\FilamentEvents\Resources\VenueSpaceResource\Pages\ListVenueSpaces;
use AIArmada\FilamentEvents\Resources\VenueSpaceResource\Pages\ViewVenueSpace;
use AIArmada\FilamentEvents\Resources\VenueSpaceResource\Schemas\VenueSpaceForm;
use AIArmada\FilamentEvents\Resources\VenueSpaceResource\Schemas\VenueSpaceInfolist;
use AIArmada\FilamentEvents\Resources\VenueSpaceResource\Tables\VenueSpacesTable;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class VenueSpaceResource extends Resource
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return class-string<VenueSpace>
     */
    public static function getModel(): string
    {
        return ModelResolver::venueSpaceClass();
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.resources.navigation_group.venue_space')
            ?? config('filament-events.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-events.resources.navigation_sort.venue_space');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function form(Schema $schema): Schema
    {
        return VenueSpaceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VenueSpaceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VenueSpacesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVenueSpaces::route('/'),
            'create' => CreateVenueSpace::route('/create'),
            'view' => ViewVenueSpace::route('/{record}'),
            'edit' => EditVenueSpace::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, class-string<RelationManager>>
     */
    public static function getRelations(): array
    {
        $configuredRelations = config('filament-events.resources.venue_space_relation_managers', []);

        if (! is_array($configuredRelations)) {
            return [];
        }

        $relations = [];

        foreach ($configuredRelations as $relationClass) {
            if (is_string($relationClass) && is_a($relationClass, RelationManager::class, true)) {
                $relations[] = $relationClass;
            }
        }

        return array_values(array_unique($relations));
    }
}
