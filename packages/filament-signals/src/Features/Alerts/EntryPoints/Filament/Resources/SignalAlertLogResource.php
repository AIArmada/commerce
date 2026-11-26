<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources;

use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertLogResource\Pages;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertLogResource\Schemas\SignalAlertLogForm;
use AIArmada\FilamentSignals\Features\Alerts\EntryPoints\Filament\Resources\SignalAlertLogResource\Tables\SignalAlertLogTable;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertLog;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class SignalAlertLogResource extends Resource
{
    protected static ?string $model = SignalAlertLog::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bell';

    /**
     * @return Builder<SignalAlertLog>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = SignalAlertLog::query()->forOwner()->with('alertRule');

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
        return (int) config('filament-signals.resources.navigation_sort.alert_logs', 34);
    }

    public static function form(Schema $schema): Schema
    {
        return SignalAlertLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SignalAlertLogTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSignalAlertLogs::route('/'),
        ];
    }
}
