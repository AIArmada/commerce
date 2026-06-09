<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Models\Venue;
use AIArmada\FilamentEvents\Resources\VenueResource\Pages;
use AIArmada\FilamentEvents\Resources\VenueResource\Schemas\VenueForm;
use AIArmada\FilamentEvents\Resources\VenueResource\Schemas\VenueInfolist;
use AIArmada\FilamentEvents\Resources\VenueResource\Tables\VenueTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class VenueResource extends Resource
{
    protected static ?string $model = Venue::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('filament-events.navigation.group', 'Events');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-events.navigation.resources.venues', 4);
    }

    /**
     * @return Builder<Venue>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Venue> $query */
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false)
            ->withCount('occurrences');
    }

    public static function form(Schema $schema): Schema
    {
        return VenueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VenueTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VenueInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVenues::route('/'),
            'create' => Pages\CreateVenue::route('/create'),
            'view' => Pages\ViewVenue::route('/{record}'),
            'edit' => Pages\EditVenue::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'city', 'state', 'country'];
    }
}
