<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralRewardResource\Schemas;

use AIArmada\FilamentReferrals\Support\ReferralAdminPresenter;
use AIArmada\Referrals\Models\ReferralReward;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReferralRewardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('program.name'),
            TextEntry::make('invite_id')
                ->label('Invite')
                ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::shortId(is_string($state) ? $state : null)),
            TextEntry::make('side')
                ->badge()
                ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::statusLabel($state)),
            TextEntry::make('beneficiary_label')
                ->label('Beneficiary')
                ->state(fn (ReferralReward $record): string => ReferralAdminPresenter::morphLabel($record->beneficiary)),
            TextEntry::make('driver')
                ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::statusLabel(is_string($state) ? $state : null)),
            TextEntry::make('value')
                ->label('Amount / points')
                ->state(fn (ReferralReward $record): string => ReferralAdminPresenter::rewardValue($record)),
            TextEntry::make('status')
                ->badge()
                ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::statusLabel($state))
                ->color(fn (mixed $state): string => ReferralAdminPresenter::statusColor($state)),
            TextEntry::make('external_reference'),
            TextEntry::make('fulfilled_at')->dateTime(),
            TextEntry::make('reversed_at')->dateTime(),
        ])->columns(2);
    }
}
