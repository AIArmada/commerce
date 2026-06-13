<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SignalAlertRuleResource\Pages;
use AIArmada\FilamentSignals\Resources\SignalAlertRuleResource\Schemas\SignalAlertRuleForm;
use AIArmada\FilamentSignals\Resources\SignalAlertRuleResource\Tables\SignalAlertRuleTable;
use AIArmada\Signals\Models\SignalAlertRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class SignalAlertRuleResource extends Resource
{
    protected static ?string $model = SignalAlertRule::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?int $navigationSort = 33;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<SignalAlertRule>
     */
    public static function getEloquentQuery(): Builder
    {
        return SignalAlertRule::query()->forOwner()->with('trackedProperty');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-signals.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.alert_rules', 33);
    }

    public static function form(Schema $schema): Schema
    {
        return SignalAlertRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SignalAlertRuleTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSignalAlertRules::route('/'),
            'create' => Pages\CreateSignalAlertRule::route('/create'),
            'edit' => Pages\EditSignalAlertRule::route('/{record}/edit'),
        ];
    }
}
