<?php

declare(strict_types=1);

namespace AIArmada\Rewards;

use AIArmada\Rewards\Commands\ApplyRewardSourceCorrectionsCommand;
use AIArmada\Rewards\Commands\ExpireRewardPointLotsCommand;
use AIArmada\Rewards\Contracts\RewardBenefitCatalog;
use AIArmada\Rewards\Contracts\RewardBenefitCouponFulfillment;
use AIArmada\Rewards\Contracts\RewardCorrectionKeySource;
use AIArmada\Rewards\Contracts\RewardFinancialReadinessChecker;
use AIArmada\Rewards\Contracts\RewardGratitudePlanner;
use AIArmada\Rewards\Contracts\RewardPayoutExecutor;
use AIArmada\Rewards\Contracts\RewardPayoutMethodCatalog;
use AIArmada\Rewards\Contracts\RewardPreviewEvaluator;
use AIArmada\Rewards\Contracts\RewardReadinessChecker;
use AIArmada\Rewards\Contracts\RewardRecipientResolver;
use AIArmada\Rewards\Contracts\RewardReservationResolver;
use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use AIArmada\Rewards\Support\ManualRewardPayoutExecutor;
use AIArmada\Rewards\Support\ManualRewardPayoutMethodCatalog;
use AIArmada\Rewards\Support\MorphRewardRecipientResolver;
use AIArmada\Rewards\Support\NullRewardBenefitCouponFulfillment;
use AIArmada\Rewards\Support\NullRewardCorrectionKeySource;
use AIArmada\Rewards\Support\NullRewardFinancialReadinessChecker;
use AIArmada\Rewards\Support\NullRewardGratitudePlanner;
use AIArmada\Rewards\Support\NullRewardReadinessChecker;
use AIArmada\Rewards\Support\NullRewardReservationResolver;
use AIArmada\Rewards\Support\NullRewardSourceFactReader;
use AIArmada\Rewards\Support\RewardBenefitCatalogService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class RewardsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('rewards')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                ExpireRewardPointLotsCommand::class,
                ApplyRewardSourceCorrectionsCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        // Fail-closed defaults; host overrides win. AsAction classes are
        // container-resolved per ::run() and need no registration.
        $this->app->bind(RewardSourceFactReader::class, NullRewardSourceFactReader::class);
        $this->app->bind(RewardReservationResolver::class, NullRewardReservationResolver::class);
        $this->app->bind(RewardReadinessChecker::class, NullRewardReadinessChecker::class);
        $this->app->bind(RewardFinancialReadinessChecker::class, NullRewardFinancialReadinessChecker::class);
        $this->app->bind(RewardBenefitCatalog::class, RewardBenefitCatalogService::class);
        $this->app->bind(RewardPayoutExecutor::class, ManualRewardPayoutExecutor::class);
        $this->app->bind(RewardPayoutMethodCatalog::class, ManualRewardPayoutMethodCatalog::class);
        $this->app->bind(RewardBenefitCouponFulfillment::class, NullRewardBenefitCouponFulfillment::class);
        $this->app->bind(RewardRecipientResolver::class, MorphRewardRecipientResolver::class);
        $this->app->bind(RewardCorrectionKeySource::class, NullRewardCorrectionKeySource::class);
        $this->app->bind(RewardGratitudePlanner::class, NullRewardGratitudePlanner::class);

        $this->app->bind(RewardPreviewEvaluator::class, Support\RewardPreviewEngine::class);
    }
}
