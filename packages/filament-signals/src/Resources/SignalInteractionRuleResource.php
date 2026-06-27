<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource\Pages;
use AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource\Schemas\SignalInteractionRuleForm;
use AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource\Tables\SignalInteractionRuleTable;
use AIArmada\Signals\Models\SignalInteractionRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class SignalInteractionRuleResource extends Resource
{
    protected static ?string $model = SignalInteractionRule::class;

    protected static ?string $modelLabel = 'Interaction Rule';

    protected static ?string $pluralModelLabel = 'Interaction Rules';

    protected static ?string $navigationLabel = 'Interaction Rules';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cursor-arrow-rays';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<SignalInteractionRule>
     */
    public static function getEloquentQuery(): Builder
    {
        return SignalInteractionRule::query()->forOwner()->with('trackedProperty');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-signals.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.interaction_rules', 35);
    }

    public static function form(Schema $schema): Schema
    {
        return SignalInteractionRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SignalInteractionRuleTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSignalInteractionRules::route('/'),
            'create' => Pages\CreateSignalInteractionRule::route('/create'),
            'edit' => Pages\EditSignalInteractionRule::route('/{record}/edit'),
        ];
    }
}
