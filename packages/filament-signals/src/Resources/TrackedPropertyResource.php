<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\TrackedPropertyResource\Pages;
use AIArmada\FilamentSignals\Resources\TrackedPropertyResource\Schemas\TrackedPropertyForm;
use AIArmada\FilamentSignals\Resources\TrackedPropertyResource\Tables\TrackedPropertyTable;
use AIArmada\Signals\Models\TrackedProperty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class TrackedPropertyResource extends Resource
{
    protected static ?string $model = TrackedProperty::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rss';

    protected static string | UnitEnum | null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<TrackedProperty>
     */
    public static function getEloquentQuery(): Builder
    {
        return TrackedProperty::query()->forOwner();
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-signals.navigation_group', 'Insights');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.properties', 30);
    }

    public static function form(Schema $schema): Schema
    {
        return TrackedPropertyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrackedPropertyTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrackedProperties::route('/'),
            'create' => Pages\CreateTrackedProperty::route('/create'),
            'edit' => Pages\EditTrackedProperty::route('/{record}/edit'),
        ];
    }
}
