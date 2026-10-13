<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralInviteResource\Schemas;

use AIArmada\FilamentReferrals\Support\ReferralAdminPresenter;
use AIArmada\Referrals\Models\ReferralInvite;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReferralInviteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('program.name'),
            TextEntry::make('referrer_label')
                ->label('Referrer')
                ->state(fn (ReferralInvite $record): string => ReferralAdminPresenter::morphLabel($record->referrer)),
            TextEntry::make('invitee_hash')
                ->label('Invitee hash')
                ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::shortHash(is_string($state) ? $state : null)),
            TextEntry::make('status')
                ->badge()
                ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::statusLabel($state))
                ->color(fn (mixed $state): string => ReferralAdminPresenter::statusColor($state)),
            TextEntry::make('accepted_by_label')
                ->label('Accepted by')
                ->state(fn (ReferralInvite $record): string => ReferralAdminPresenter::morphLabel($record->acceptedBy)),
            TextEntry::make('accepted_at')->dateTime(),
            TextEntry::make('qualification_business_key'),
            TextEntry::make('qualified_at')->dateTime(),
            TextEntry::make('rewarded_at')->dateTime(),
            TextEntry::make('expired_at')
                ->label('Expiry')
                ->dateTime(),
            TextEntry::make('revoked_at')->dateTime(),
        ])->columns(2);
    }
}
