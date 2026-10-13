<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Widgets;

use AIArmada\FilamentReferrals\Support\ReferralFilamentScope;
use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Enums\ReferralRewardStatus;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralReward;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReferralStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $programIds = ReferralFilamentScope::scopedProgramIds();

        return [
            Stat::make(
                'Pending invites',
                ReferralInvite::query()
                    ->whereIn('program_id', $programIds)
                    ->where('status', ReferralInviteStatus::Pending->value)
                    ->count(),
            ),
            Stat::make(
                'Qualified invites',
                ReferralInvite::query()
                    ->whereIn('program_id', $programIds)
                    ->where('status', ReferralInviteStatus::Qualified->value)
                    ->count(),
            ),
            Stat::make(
                'Rewarded invites',
                ReferralInvite::query()
                    ->whereIn('program_id', $programIds)
                    ->where('status', ReferralInviteStatus::Rewarded->value)
                    ->count(),
            ),
            Stat::make(
                'Rewards pending fulfilment',
                ReferralReward::query()
                    ->whereIn('program_id', $programIds)
                    ->whereIn('status', [
                        ReferralRewardStatus::Pending->value,
                        ReferralRewardStatus::Failed->value,
                    ])
                    ->count(),
            ),
        ];
    }
}
