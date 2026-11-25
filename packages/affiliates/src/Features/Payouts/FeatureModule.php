<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts;

use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(Api\PayoutEventHistory::class, Queries\ReadPayoutEventHistory::class);
        $app->bind(Api\PayoutAdministration::class, Actions\AdministerPayout::class);
        $app->bind(Api\PayoutRemoval::class, Actions\DeleteUnusedPayout::class);
        $app->bind(Api\PayoutReconciliation::class, Actions\ReconcilePayout::class);
        $app->bind(Api\PayoutReconciliationQueries::class, Queries\PayoutReconciliationQuery::class);
        $app->bind(Api\PayoutReversal::class, Actions\ReverseStripePayout::class);
        $app->bind(Api\PayoutRelease::class, Actions\ReleasePayoutFunds::class);
        $app->bind(Api\ScheduledPayoutSweep::class, Actions\RunScheduledPayoutSweep::class);
        $app->bind(Api\PayoutHoldReader::class, Queries\ReadPayoutHolds::class);
        $app->bind(Api\PayoutHoldAdministration::class, Actions\AdministerPayoutHold::class);
        $app->bind(Api\PayoutMethodAdministration::class, Actions\AdministerPayoutMethod::class);
    }

    public function boot(Application $app): void {}
}
