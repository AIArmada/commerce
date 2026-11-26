<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Resources;

use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Resources\SavedSignalReportResource\Pages;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Resources\SavedSignalReportResource\Schemas\SavedSignalReportForm;
use AIArmada\FilamentSignals\Features\Reporting\EntryPoints\Filament\Resources\SavedSignalReportResource\Tables\SavedSignalReportTable;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use AIArmada\Signals\Features\Reporting\Models\SavedSignalReport;
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

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        $query = SavedSignalReport::query()->forOwner()->with('segment');

        $property = TrackedProperty::query()->forOwner();
        $property->select($property->getModel()->qualifyColumn('name'))
            ->whereColumn($property->getModel()->qualifyColumn('id'), $query->getModel()->qualifyColumn('tracked_property_id'));

        return $query->select($query->getModel()->qualifyColumn('*'))->addSelect(['tracked_property_name' => $property]);
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-signals.navigation.group');
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
