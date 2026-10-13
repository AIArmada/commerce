<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardPayoutResource;

use AIArmada\FilamentRewards\Resources\RewardPayoutResource\Pages\ListRewardPayouts;
use AIArmada\FilamentRewards\Resources\RewardPayoutResource\Tables\RewardPayoutsTable;
use AIArmada\FilamentRewards\Support\RewardFilamentScope;
use AIArmada\Rewards\Models\RewardPayoutRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

class RewardPayoutResource extends Resource
{
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-rewards.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-rewards.navigation.sort.payouts');
    }

    protected static ?string $model = RewardPayoutRequest::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $navigationLabel = 'Payouts';

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return RewardFilamentScope::applyProgramColumn(parent::getEloquentQuery());
    }

    public static function table(Table $table): Table
    {
        return RewardPayoutsTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListRewardPayouts::route('/'),
        ];
    }
}
