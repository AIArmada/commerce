<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardRecoveryResource;

use AIArmada\FilamentRewards\Resources\RewardRecoveryResource\Pages\ListRewardRecoveries;
use AIArmada\FilamentRewards\Resources\RewardRecoveryResource\Tables\RewardRecoveriesTable;
use AIArmada\FilamentRewards\Support\RewardFilamentScope;
use AIArmada\Rewards\Models\RewardRecovery;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

class RewardRecoveryResource extends Resource
{
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-rewards.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-rewards.navigation.sort.recoveries');
    }

    protected static ?string $model = RewardRecovery::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Recoveries';

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return RewardFilamentScope::applyViaAward(parent::getEloquentQuery());
    }

    public static function table(Table $table): Table
    {
        return RewardRecoveriesTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListRewardRecoveries::route('/'),
        ];
    }
}
