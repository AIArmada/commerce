<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralRewardResource;

use AIArmada\FilamentReferrals\Resources\ReferralRewardResource\Pages\ListReferralRewards;
use AIArmada\FilamentReferrals\Resources\ReferralRewardResource\Pages\ViewReferralReward;
use AIArmada\FilamentReferrals\Resources\ReferralRewardResource\Schemas\ReferralRewardInfolist;
use AIArmada\FilamentReferrals\Resources\ReferralRewardResource\Tables\ReferralRewardsTable;
use AIArmada\FilamentReferrals\Support\ReferralFilamentScope;
use AIArmada\Referrals\Models\ReferralReward;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

class ReferralRewardResource extends Resource
{
    protected static ?string $model = ReferralReward::class;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-referrals.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-referrals.navigation.sort.rewards');
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return ReferralRewardInfolist::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ReferralRewardsTable::configure($table);
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return ReferralFilamentScope::applyProgramColumn(
            parent::getEloquentQuery()->with(['program', 'invite', 'beneficiary']),
        );
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListReferralRewards::route('/'),
            'view' => ViewReferralReward::route('/{record}'),
        ];
    }
}
