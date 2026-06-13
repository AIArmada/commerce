<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SignalAlertLogResource\Pages;
use AIArmada\FilamentSignals\Resources\SignalAlertLogResource\Schemas\SignalAlertLogForm;
use AIArmada\FilamentSignals\Resources\SignalAlertLogResource\Tables\SignalAlertLogTable;
use AIArmada\Signals\Models\SignalAlertLog;
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

    protected static ?int $navigationSort = 34;

    /**
     * @return Builder<SignalAlertLog>
     */
    public static function getEloquentQuery(): Builder
    {
        return SignalAlertLog::query()->forOwner()->with(['alertRule', 'trackedProperty']);
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
