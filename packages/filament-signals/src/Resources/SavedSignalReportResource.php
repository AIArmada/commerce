<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SavedSignalReportResource\Pages;
use AIArmada\FilamentSignals\Resources\SavedSignalReportResource\Schemas\SavedSignalReportForm;
use AIArmada\FilamentSignals\Resources\SavedSignalReportResource\Tables\SavedSignalReportTable;
use AIArmada\Signals\Models\SavedSignalReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class SavedSignalReportResource extends Resource
{
    protected static ?string $model = SavedSignalReport::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bookmark-square';

    protected static string | UnitEnum | null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 32;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['trackedProperty', 'segment']);
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-signals.navigation_group', 'Insights');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.saved_reports', 32);
    }

    public static function form(Schema $schema): Schema
    {
        return SavedSignalReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SavedSignalReportTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSavedSignalReports::route('/'),
            'create' => Pages\CreateSavedSignalReport::route('/create'),
            'edit' => Pages\EditSavedSignalReport::route('/{record}/edit'),
        ];
    }
}
