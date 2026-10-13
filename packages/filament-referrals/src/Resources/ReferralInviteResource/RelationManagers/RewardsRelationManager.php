<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralInviteResource\RelationManagers;

use AIArmada\FilamentReferrals\Resources\ReferralRewardResource\Tables\ReferralRewardsTable;
use AIArmada\FilamentReferrals\Support\ReferralFilamentScope;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RewardsRelationManager extends RelationManager
{
    protected static string $relationship = 'rewards';

    public function table(Table $table): Table
    {
        return ReferralRewardsTable::configure($table, showProgramFilter: false, showViewAction: false)
            ->modifyQueryUsing(
                static fn (Builder $query): Builder => ReferralFilamentScope::applyProgramColumn($query),
            );
    }
}
