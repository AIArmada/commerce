<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardBenefitGrantResource;

use AIArmada\FilamentRewards\Queries\RewardProgramScopeQuery;
use AIArmada\FilamentRewards\Resources\RewardBenefitGrantResource\Pages\ListRewardBenefitGrants;
use AIArmada\FilamentRewards\Resources\RewardBenefitGrantResource\Tables\RewardBenefitGrantsTable;
use AIArmada\FilamentRewards\Resources\StaffRewardResource;
use AIArmada\Rewards\Features\Benefits\Models\RewardBenefitGrant;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

class RewardBenefitGrantResource extends StaffRewardResource
{
    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-rewards.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-rewards.navigation.sort.benefit_grants');
    }

    protected static ?string $model = RewardBenefitGrant::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $navigationLabel = 'Benefit grants';

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return RewardProgramScopeQuery::applyViaItem(parent::getEloquentQuery());
    }

    public static function table(Table $table): Table
    {
        return RewardBenefitGrantsTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListRewardBenefitGrants::route('/'),
        ];
    }
}
