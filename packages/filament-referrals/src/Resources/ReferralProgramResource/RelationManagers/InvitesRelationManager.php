<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralProgramResource\RelationManagers;

use AIArmada\FilamentReferrals\Resources\ReferralInviteResource\Tables\ReferralInvitesTable;
use AIArmada\FilamentReferrals\Support\ReferralFilamentScope;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvitesRelationManager extends RelationManager
{
    protected static string $relationship = 'invites';

    public function table(Table $table): Table
    {
        return ReferralInvitesTable::configure($table, showActions: false, showFilters: false)
            ->modifyQueryUsing(
                static fn (Builder $query): Builder => ReferralFilamentScope::applyProgramColumn($query),
            );
    }
}
