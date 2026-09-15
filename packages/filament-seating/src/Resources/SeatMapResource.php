<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentSeating\Schemas\SeatMapFormSchema;
use AIArmada\FilamentSeating\Tables\SeatMapTable;
use AIArmada\Seating\Models\SeatMap as SeatMapModel;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class SeatMapResource extends Resource
{
    protected static ?string $model = SeatMapModel::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-map';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-seating.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-seating.resources.navigation_sort.seat_map');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false)
            ->withCount('sections');
    }

    public static function canViewAny(): bool
    {
        return FilamentPermission::hasAbility('seat-map.viewAny');
    }

    public static function canView(Model $record): bool
    {
        return FilamentPermission::hasAbility('seat-map.view');
    }

    public static function canCreate(): bool
    {
        return FilamentPermission::hasAbility('seat-map.create');
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentPermission::hasAbility('seat-map.update');
    }

    public static function canDelete(Model $record): bool
    {
        return FilamentPermission::hasAbility('seat-map.delete');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(SeatMapFormSchema::make());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(SeatMapTable::columns())
            ->filters(SeatMapTable::filters())
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Seat Map Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug'),
                        TextEntry::make('version'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ])->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => SeatMapResource\Pages\ListSeatMaps::route('/'),
            'create' => SeatMapResource\Pages\CreateSeatMap::route('/create'),
            'view' => SeatMapResource\Pages\ViewSeatMap::route('/{record}'),
            'edit' => SeatMapResource\Pages\EditSeatMap::route('/{record}/edit'),
        ];
    }
}
