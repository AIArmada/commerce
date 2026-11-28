<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Payments;

use AIArmada\Docs\Contracts\DocumentPaymentRecorder;
use AIArmada\Docs\Features\Payments\Actions\RecordDocPayment;
use AIArmada\Docs\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(DocumentPaymentRecorder::class, RecordDocPayment::class);
    }

    public function boot(Application $app): void {}
}
