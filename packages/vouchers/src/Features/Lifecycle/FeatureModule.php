<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Features\Lifecycle;

use AIArmada\Vouchers\Events\VoucherApplied;
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
    }

    public function boot(Application $app): void
    {
        $app->make(Dispatcher::class)->listen(VoucherApplied::class, IncrementVoucherAppliedCount::class);
    }
}
