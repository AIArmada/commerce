<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SignalGoalResource\Pages;
use AIArmada\FilamentSignals\Resources\SignalGoalResource\Schemas\SignalGoalForm;
use AIArmada\FilamentSignals\Resources\SignalGoalResource\Tables\SignalGoalTable;
use AIArmada\Signals\Models\SignalGoal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class SignalGoalResource extends Resource
{
    protected static ?string $model = SignalGoal::class;

    protected static ?string $modelLabel = 'Goal';

    protected static ?string $pluralModelLabel = 'Goals';

    protected static ?string $navigationLabel = 'Goals';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 31;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<SignalGoal>
     */
    public static function getEloquentQuery(): Builder
    {
        return SignalGoal::query()->forOwner()->with('trackedProperty');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-signals.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.goals', 31);
    }

    public static function form(Schema $schema): Schema
    {
        return SignalGoalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SignalGoalTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSignalGoals::route('/'),
            'create' => Pages\CreateSignalGoal::route('/create'),
            'edit' => Pages\EditSignalGoal::route('/{record}/edit'),
        ];
    }
}
