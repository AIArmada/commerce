<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Models\EventSeries;
use AIArmada\FilamentEvents\Resources\EventSeriesResource\Pages;
use AIArmada\FilamentEvents\Resources\EventSeriesResource\Schemas\EventSeriesForm;
use AIArmada\FilamentEvents\Resources\EventSeriesResource\Schemas\EventSeriesInfolist;
use AIArmada\FilamentEvents\Resources\EventSeriesResource\Tables\EventSeriesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class EventSeriesResource extends Resource
{
    protected static ?string $model = EventSeries::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('filament-events.navigation.group', 'Events');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-events.navigation.resources.series', 1);
    }

    /**
     * @return Builder<EventSeries>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<EventSeries> $query */
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false)
            ->withCount('events');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('is_active', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return EventSeriesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventSeriesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EventSeriesInfolist::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventSeries::route('/'),
            'create' => Pages\CreateEventSeries::route('/create'),
            'view' => Pages\ViewEventSeries::route('/{record}'),
            'edit' => Pages\EditEventSeries::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'description'];
    }
}
