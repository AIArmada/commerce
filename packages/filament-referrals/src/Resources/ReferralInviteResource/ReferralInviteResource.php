<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralInviteResource;

use AIArmada\FilamentReferrals\Resources\ReferralInviteResource\Pages\ListReferralInvites;
use AIArmada\FilamentReferrals\Resources\ReferralInviteResource\Pages\ViewReferralInvite;
use AIArmada\FilamentReferrals\Resources\ReferralInviteResource\RelationManagers\RewardsRelationManager;
use AIArmada\FilamentReferrals\Resources\ReferralInviteResource\Schemas\ReferralInviteInfolist;
use AIArmada\FilamentReferrals\Resources\ReferralInviteResource\Tables\ReferralInvitesTable;
use AIArmada\FilamentReferrals\Support\ReferralFilamentScope;
use AIArmada\Referrals\Models\ReferralInvite;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

class ReferralInviteResource extends Resource
{
    protected static ?string $model = ReferralInvite::class;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-referrals.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-referrals.navigation.sort.invites');
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return ReferralInviteInfolist::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ReferralInvitesTable::configure($table);
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return ReferralFilamentScope::applyProgramColumn(
            parent::getEloquentQuery()->with(['program', 'referrer', 'acceptedBy']),
        );
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            RewardsRelationManager::class,
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListReferralInvites::route('/'),
            'view' => ViewReferralInvite::route('/{record}'),
        ];
    }
}
