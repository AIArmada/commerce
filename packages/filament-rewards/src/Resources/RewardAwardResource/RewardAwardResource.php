<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardAwardResource;

use AIArmada\FilamentRewards\Resources\RewardAwardResource\Pages\ListRewardAwards;
use AIArmada\FilamentRewards\Resources\RewardAwardResource\Pages\ViewRewardAward;
use AIArmada\FilamentRewards\Resources\RewardAwardResource\Schemas\RewardAwardInfolist;
use AIArmada\FilamentRewards\Resources\RewardAwardResource\Tables\RewardAwardsTable;
use AIArmada\FilamentRewards\Support\RewardFilamentScope;
use AIArmada\Rewards\Models\RewardAward;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

class RewardAwardResource extends Resource
{
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-rewards.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-rewards.navigation.sort.awards');
    }

    protected static ?string $model = RewardAward::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCheckBadge;

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return RewardAwardInfolist::configure($schema);
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return RewardFilamentScope::applyProgramColumn(parent::getEloquentQuery());
    }

    public static function table(Table $table): Table
    {
        return RewardAwardsTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListRewardAwards::route('/'),
            'view' => ViewRewardAward::route('/{record}'),
        ];
    }
}
