<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardFundingResource;

use AIArmada\FilamentRewards\Resources\RewardFundingResource\Pages\ListRewardFunding;
use AIArmada\FilamentRewards\Resources\RewardFundingResource\Tables\RewardFundingTable;
use AIArmada\FilamentRewards\Support\RewardFilamentScope;
use AIArmada\Rewards\Models\RewardFundingEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

class RewardFundingResource extends Resource
{
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-rewards.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-rewards.navigation.sort.funding');
    }

    protected static ?string $model = RewardFundingEntry::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Funding';

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return RewardFilamentScope::applyProgramColumn(parent::getEloquentQuery());
    }

    public static function table(Table $table): Table
    {
        return RewardFundingTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListRewardFunding::route('/'),
        ];
    }
}
