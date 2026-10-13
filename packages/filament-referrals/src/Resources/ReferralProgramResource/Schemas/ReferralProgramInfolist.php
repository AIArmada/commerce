<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Schemas;

use AIArmada\FilamentReferrals\Support\ReferralAdminPresenter;
use AIArmada\Referrals\Models\ReferralProgram;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ReferralProgramInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('slug'),
            TextEntry::make('status')
                ->badge()
                ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::statusLabel($state))
                ->color(fn (mixed $state): string => ReferralAdminPresenter::statusColor($state)),
            TextEntry::make('qualifier_outcome'),
            TextEntry::make('currency'),
            TextEntry::make('invite_expiry_days'),
            TextEntry::make('max_invites_per_referrer'),
            TextEntry::make('max_qualified_per_referrer_per_month'),
            TextEntry::make('starts_at')->dateTime(),
            TextEntry::make('ends_at')->dateTime(),
            TextEntry::make('referrer_reward')
                ->state(fn (ReferralProgram $record): string => self::rewardSpec($record->referrer_reward)),
            TextEntry::make('referee_reward')
                ->state(fn (ReferralProgram $record): string => self::rewardSpec($record->referee_reward)),
        ])->columns(2);
    }

    /**
     * @param  array<string, mixed>|null  $spec
     */
    private static function rewardSpec(?array $spec): string
    {
        return $spec === null
            ? 'No reward'
            : ReferralAdminPresenter::statusLabel(is_string($spec['driver'] ?? null) ? $spec['driver'] : null);
    }
}
