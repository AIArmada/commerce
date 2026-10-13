<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardBenefitItemResource;

use AIArmada\FilamentRewards\Resources\RewardBenefitItemResource\Pages\ListRewardBenefitItems;
use AIArmada\FilamentRewards\Resources\RewardBenefitItemResource\Tables\RewardBenefitItemsTable;
use AIArmada\FilamentRewards\Support\RewardFilamentScope;
use AIArmada\Rewards\Models\RewardBenefitItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

class RewardBenefitItemResource extends Resource
{
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-rewards.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-rewards.navigation.sort.benefit_items');
    }

    protected static ?string $model = RewardBenefitItem::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $navigationLabel = 'Benefit catalog';

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return RewardFilamentScope::applyProgramColumn(parent::getEloquentQuery());
    }

    public static function table(Table $table): Table
    {
        return RewardBenefitItemsTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListRewardBenefitItems::route('/'),
        ];
    }
}
