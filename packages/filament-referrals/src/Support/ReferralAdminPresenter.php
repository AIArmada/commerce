<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Support;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Enums\ReferralProgramStatus;
use AIArmada\Referrals\Enums\ReferralRewardStatus;
use AIArmada\Referrals\Models\ReferralReward;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

final class ReferralAdminPresenter
{
    public static function morphLabel(?Model $model): string
    {
        if ($model === null) {
            return 'Unknown';
        }

        foreach (['name', 'display_name', 'username', 'email', 'title'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && mb_trim($value) !== '') {
                return mb_trim($value);
            }
        }

        return self::shortId((string) $model->getKey());
    }

    public static function shortId(?string $id): string
    {
        if ($id === null || $id === '') {
            return '—';
        }

        return Str::limit($id, 8, '');
    }

    public static function shortHash(?string $hash): string
    {
        if ($hash === null || $hash === '') {
            return '—';
        }

        return Str::limit($hash, 12, '');
    }

    /**
     * @param  BackedEnum|string|null  $status
     */
    public static function statusLabel(mixed $status): string
    {
        $value = $status instanceof BackedEnum ? (string) $status->value : $status;

        return is_string($value) && $value !== '' ? Str::headline($value) : 'Unknown';
    }

    public static function statusColor(mixed $status): string
    {
        $value = $status instanceof BackedEnum ? (string) $status->value : $status;

        return match ($value) {
            ReferralProgramStatus::Active->value,
            ReferralInviteStatus::Rewarded->value,
            ReferralRewardStatus::Fulfilled->value => 'success',
            ReferralProgramStatus::Paused->value,
            ReferralInviteStatus::Accepted->value,
            ReferralInviteStatus::Qualified->value => 'warning',
            ReferralProgramStatus::Archived->value,
            ReferralInviteStatus::Expired->value,
            ReferralInviteStatus::Revoked->value,
            ReferralRewardStatus::Reversed->value,
            ReferralRewardStatus::Failed->value => 'danger',
            default => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function driverOptions(): array
    {
        return [
            'manual' => 'Manual',
            'voucher' => 'Voucher',
            'affiliate_cash' => 'Affiliate cash',
            'reward_points' => 'Reward points',
        ];
    }

    public static function rewardValue(ReferralReward $reward): string
    {
        if ($reward->amount_minor !== null) {
            return MoneyFormatter::formatMinor($reward->amount_minor, $reward->currency);
        }

        if ($reward->points !== null) {
            return Number::format($reward->points) . ' points';
        }

        return '—';
    }
}
