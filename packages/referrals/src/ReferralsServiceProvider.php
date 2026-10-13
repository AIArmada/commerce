<?php

declare(strict_types=1);

namespace AIArmada\Referrals;

use AIArmada\Referrals\Console\Commands\ExpireReferralInvitesCommand;
use AIArmada\Referrals\Contracts\ReferralAffiliateResolver;
use AIArmada\Referrals\Contracts\ReferralRewardFulfillment;
use AIArmada\Referrals\Fulfillment\AffiliatesCashFulfillment;
use AIArmada\Referrals\Fulfillment\ManualReferralFulfillment;
use AIArmada\Referrals\Fulfillment\ReferralRewardFulfillmentRouter;
use AIArmada\Referrals\Fulfillment\RewardsPointsFulfillment;
use AIArmada\Referrals\Fulfillment\VouchersReferralFulfillment;
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
                'AIArmada\\Affiliates\\Models\\Affiliate',
                'AIArmada\\Affiliates\\Actions\\Conversions\\RecordAffiliateAward',
                'AIArmada\\Affiliates\\Actions\\Conversions\\ReverseAffiliateConversion',
                'AIArmada\\Affiliates\\Contracts\\AffiliateAwardPolicy',
            ])) {
            $this->app->singleton(AffiliatesCashFulfillment::class);
            $drivers['affiliate_cash'] = AffiliatesCashFulfillment::class;
        }

        if (is_array($configuredDrivers['reward_points'] ?? null)
            && ($configuredDrivers['reward_points']['enabled'] ?? false)
            && $this->symbolsExist([
                'AIArmada\\Rewards\\Jobs\\EvaluateRewardEntitlementJob',
                'AIArmada\\Rewards\\Contracts\\RewardSourceFactReader',
                'AIArmada\\Rewards\\Support\\RewardSourceFact',
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
