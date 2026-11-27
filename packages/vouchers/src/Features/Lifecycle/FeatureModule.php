<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle;

use AIArmada\Vouchers\Events\VoucherApplied;
use AIArmada\Vouchers\Features\Lifecycle\EntryPoints\AffiliateIntegrationRegistrar;
use AIArmada\Vouchers\Features\Lifecycle\Persistence\VoucherLookupCache;
use AIArmada\Vouchers\Runtime\VoucherLookup;
use AIArmada\Vouchers\Features\Lifecycle\Actions\GenerateVoucherBatch;
use AIArmada\Vouchers\Runtime\VoucherBatchIssuer;
use AIArmada\Vouchers\Features\Lifecycle\Actions\ChangeVoucherAvailability;
use AIArmada\Vouchers\Runtime\VoucherAvailability;
use AIArmada\Vouchers\Features\Lifecycle\Actions\ExpireVoucher;
use AIArmada\Vouchers\Runtime\VoucherExpiry;
use AIArmada\Vouchers\Features\Lifecycle\Actions\CreateVoucher;
use AIArmada\Vouchers\Features\Lifecycle\Actions\UpdateVoucher;
use AIArmada\Vouchers\Runtime\VoucherCreator;
use AIArmada\Vouchers\Runtime\VoucherDeletion;
use AIArmada\Vouchers\Features\Lifecycle\Actions\DeleteVoucher;
use AIArmada\Vouchers\Runtime\VoucherDepletion;
use AIArmada\Vouchers\Features\Lifecycle\Actions\DepleteVoucher;
use AIArmada\Vouchers\Runtime\VoucherUpdater;
use AIArmada\Vouchers\Features\Lifecycle\Actions\RecordVoucherUsage;
use AIArmada\Vouchers\Runtime\VoucherUsageRecorder;
use AIArmada\Vouchers\Features\Lifecycle\EntryPoints\Listeners\IncrementVoucherAppliedCount;
use AIArmada\Vouchers\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(VoucherUsageRecorder::class, RecordVoucherUsage::class);
        $app->bind(VoucherCreator::class, CreateVoucher::class);
        $app->singleton(AffiliateIntegrationRegistrar::class);
        $app->singleton(VoucherLookup::class, VoucherLookupCache::class);
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
