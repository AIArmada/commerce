<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardFraudResource;

use AIArmada\FilamentRewards\Resources\RewardFraudResource\Pages\ListRewardFraudCases;
use AIArmada\FilamentRewards\Resources\RewardFraudResource\Tables\RewardFraudTable;
use AIArmada\FilamentRewards\Support\RewardFilamentScope;
use AIArmada\Rewards\Models\RewardAward;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

class RewardFraudResource extends Resource
{
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-rewards.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-rewards.navigation.sort.fraud');
    }

    protected static ?string $model = RewardAward::class;

    protected static ?string $slug = 'reward-fraud-stewardship';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Fraud stewardship';

    #[Override]
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('reward-fraud-steward');
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return RewardFilamentScope::applyProgramColumn(parent::getEloquentQuery())
            ->whereIn('status', ['held', 'pending_review'])
            ->orderByDesc('decided_at');
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return RewardFraudTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListRewardFraudCases::route('/'),
        ];
    }
}
