<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle;

use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Features\Lifecycle\Actions\ChangeVoucherAvailability;
use AIArmada\Vouchers\Features\Lifecycle\Actions\CreateVoucher;
use AIArmada\Vouchers\Features\Lifecycle\Actions\DeleteVoucher;
use AIArmada\Vouchers\Features\Lifecycle\Actions\DepleteVoucher;
use AIArmada\Vouchers\Features\Lifecycle\Actions\ExpireVoucher;
use AIArmada\Vouchers\Features\Lifecycle\Actions\GenerateVoucherBatch;
use AIArmada\Vouchers\Features\Lifecycle\Actions\RecordVoucherUsage;
use AIArmada\Vouchers\Features\Lifecycle\Actions\RedeemVoucher;
use AIArmada\Vouchers\Features\Lifecycle\Actions\UpdateVoucher;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherAvailability;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherBatchIssuer;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCatalog;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherCreator;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherDeletion;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherDepletion;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherEligibility;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherExpiry;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherRedemption;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherUpdater;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherUsageHistory;
use AIArmada\Vouchers\Features\Lifecycle\Api\VoucherUsageRecorder;
use AIArmada\Vouchers\Features\Lifecycle\EntryPoints\AffiliateIntegrationRegistrar;
use AIArmada\Vouchers\Features\Lifecycle\EntryPoints\Listeners\IncrementVoucherAppliedCount;
use AIArmada\Vouchers\Features\Lifecycle\Persistence\VoucherLookupCache;
use AIArmada\Vouchers\Features\Lifecycle\Queries\ReadVoucherCatalog;
use AIArmada\Vouchers\Features\Lifecycle\Queries\ReadVoucherEligibility;
use AIArmada\Vouchers\Features\Lifecycle\Queries\ReadVoucherUsageHistory;
use AIArmada\Vouchers\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Vouchers\Runtime\VoucherLookup;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(VoucherUsageRecorder::class, RecordVoucherUsage::class);
        $app->bind(VoucherRedemption::class, RedeemVoucher::class);
        $app->bind(VoucherCreator::class, CreateVoucher::class);
        $app->singleton(AffiliateIntegrationRegistrar::class);
        $app->singleton(VoucherLookup::class, VoucherLookupCache::class);
        $app->singleton(VoucherCatalog::class, ReadVoucherCatalog::class);
        $app->bind(VoucherEligibility::class, ReadVoucherEligibility::class);
        $app->bind(VoucherUsageHistory::class, ReadVoucherUsageHistory::class);
        $app->bind(VoucherBatchIssuer::class, GenerateVoucherBatch::class);
        $app->bind(VoucherAvailability::class, ChangeVoucherAvailability::class);
        $app->bind(VoucherExpiry::class, ExpireVoucher::class);
        $app->bind(VoucherDeletion::class, DeleteVoucher::class);
        $app->bind(VoucherDepletion::class, DepleteVoucher::class);
        $app->bind(VoucherUpdater::class, UpdateVoucher::class);
    }

    public function boot(Application $app): void
    {
        $app->make(Dispatcher::class)->listen(VoucherApplied::class, IncrementVoucherAppliedCount::class);
        $app->make(AffiliateIntegrationRegistrar::class)->register();
    }
}
