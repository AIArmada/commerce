<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions;

use AIArmada\Affiliates\Contracts\MerchantLedger;
use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->scoped(MerchantLedger::class, Actions\ExternalConversionLedger::class);
    }

    public function boot(Application $app): void {}
}
