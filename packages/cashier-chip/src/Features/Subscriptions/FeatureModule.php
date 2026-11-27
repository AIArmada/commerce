<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Features\Subscriptions;

use AIArmada\CashierChip\Billing\Cashier;
use AIArmada\CashierChip\Features\Subscriptions\Actions\ClaimRenewalAttempt;
use AIArmada\CashierChip\Features\Subscriptions\Actions\RenewSubscriptions;
use AIArmada\CashierChip\Features\Subscriptions\Actions\SyncChipPurchaseStatus;
use AIArmada\CashierChip\Features\Subscriptions\Models\Subscription;
use AIArmada\CashierChip\Features\Subscriptions\Models\SubscriptionItem;
use AIArmada\CashierChip\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);

        $app->bind(ClaimRenewalAttempt::class);
        $app->bind(RenewSubscriptions::class);
        $app->bind(SyncChipPurchaseStatus::class);
    }

    public function boot(Application $app): void {}
}
