<?php

declare(strict_types=1);

namespace AIArmada\Referrals;

use AIArmada\Referrals\Contracts\ReferralAffiliateResolver;
use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Drivers\AffiliatesCashFulfillment;
use AIArmada\Referrals\Drivers\ManualReferralFulfillment;
use AIArmada\Referrals\Drivers\ReferralRewardFulfillmentRouter;
use AIArmada\Referrals\Drivers\RewardsPointsFulfillment;
use AIArmada\Referrals\Drivers\VouchersReferralFulfillment;
use AIArmada\Referrals\EntryPoints\Commands\ExpireReferralInvitesCommand;
use AIArmada\Referrals\Support\NullReferralAffiliateResolver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ReferralsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('referrals')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                ExpireReferralInvitesCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $drivers = [];
        $configuredDrivers = config('referrals.fulfillment.drivers', []);

        if (is_array($configuredDrivers['manual'] ?? null) && ($configuredDrivers['manual']['enabled'] ?? false)) {
            $this->app->singleton(ManualReferralFulfillment::class);
            $drivers['manual'] = ManualReferralFulfillment::class;
        }

        if (is_array($configuredDrivers['voucher'] ?? null)
            && ($configuredDrivers['voucher']['enabled'] ?? false)
            && $this->symbolsExist([
                'AIArmada\\Vouchers\\Actions\\CreateVoucher',
                'AIArmada\\Vouchers\\Actions\\ExpireVoucher',
                'AIArmada\\Vouchers\\Models\\Voucher',
            ])) {
            $this->app->singleton(VouchersReferralFulfillment::class);
            $drivers['voucher'] = VouchersReferralFulfillment::class;
        }

        if (is_array($configuredDrivers['affiliate_cash'] ?? null)
            && ($configuredDrivers['affiliate_cash']['enabled'] ?? false)
            && $this->symbolsExist([
                'AIArmada\\Affiliates\\Data\\AwardRecipient',
                'AIArmada\\Affiliates\\Features\\Conversions\\Api\\AwardPosting',
                'AIArmada\\Affiliates\\Contracts\\AffiliateAwardPolicy',
            ])) {
            $this->app->singleton(AffiliatesCashFulfillment::class);
            $drivers['affiliate_cash'] = AffiliatesCashFulfillment::class;
        }

        if (is_array($configuredDrivers['reward_points'] ?? null)
            && ($configuredDrivers['reward_points']['enabled'] ?? false)
            && $this->symbolsExist([
                'AIArmada\\Rewards\\Features\\Entitlements\\EntryPoints\\Jobs\\EvaluateRewardEntitlementJob',
                'AIArmada\\Rewards\\Contracts\\RewardSourceFactReader',
                'AIArmada\\Rewards\\Data\\RewardSourceFact',
                'AIArmada\\Rewards\\Models\\RewardAward',
            ])) {
            $this->app->singleton(RewardsPointsFulfillment::class);
            $drivers['reward_points'] = RewardsPointsFulfillment::class;
        }

        $this->app->singleton(ReferralRewardFulfillment::class, function ($app) use ($drivers): ReferralRewardFulfillment {
            return new ReferralRewardFulfillmentRouter($app, $drivers);
        });
        $this->app->bind(ReferralAffiliateResolver::class, NullReferralAffiliateResolver::class);
    }

    /**
     * @param  list<string>  $symbolNames
     */
    private function symbolsExist(array $symbolNames): bool
    {
        foreach ($symbolNames as $symbolName) {
            if (! class_exists($symbolName) && ! interface_exists($symbolName)) {
                return false;
            }
        }

        return true;
    }
}
