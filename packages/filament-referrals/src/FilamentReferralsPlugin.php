<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals;

use AIArmada\FilamentReferrals\Resources\ReferralInviteResource\ReferralInviteResource;
use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\ReferralProgramResource;
use AIArmada\FilamentReferrals\Resources\ReferralRewardResource\ReferralRewardResource;
use AIArmada\FilamentReferrals\Widgets\ReferralStatsWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentReferralsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-referrals';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                ReferralProgramResource::class,
                ReferralInviteResource::class,
                ReferralRewardResource::class,
            ])
            ->widgets([
                ReferralStatsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        // No-op: referral administration uses core package actions.
    }
}
