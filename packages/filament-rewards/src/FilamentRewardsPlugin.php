<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards;

use AIArmada\FilamentRewards\Resources\RewardAppealResource\RewardAppealResource;
use AIArmada\FilamentRewards\Resources\RewardAwardResource\RewardAwardResource;
use AIArmada\FilamentRewards\Resources\RewardBenefitGrantResource\RewardBenefitGrantResource;
use AIArmada\FilamentRewards\Resources\RewardBenefitItemResource\RewardBenefitItemResource;
use AIArmada\FilamentRewards\Resources\RewardFraudResource\RewardFraudResource;
use AIArmada\FilamentRewards\Resources\RewardFundingResource\RewardFundingResource;
use AIArmada\FilamentRewards\Resources\RewardPayeeResource\RewardPayeeResource;
use AIArmada\FilamentRewards\Resources\RewardPayoutResource\RewardPayoutResource;
use AIArmada\FilamentRewards\Resources\RewardProgramResource\RewardProgramResource;
use AIArmada\FilamentRewards\Resources\RewardRecoveryResource\RewardRecoveryResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentRewardsPlugin implements Plugin
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
        return 'filament-rewards';
    }

    public function register(Panel $panel): void
    {
        // The fraud queue reads awards through the fact-reader boundary;
        // all resources register unconditionally. Coupon fulfillment and
        // automated payout rails stay host-bound contracts, not package
        // dependencies.
        $panel->resources([
            RewardProgramResource::class,
            RewardAwardResource::class,
            RewardAppealResource::class,
            RewardFundingResource::class,
            RewardPayeeResource::class,
            RewardPayoutResource::class,
            RewardBenefitItemResource::class,
            RewardBenefitGrantResource::class,
            RewardRecoveryResource::class,
            RewardFraudResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        // No-op: the service provider handles runtime integration hooks.
    }
}
