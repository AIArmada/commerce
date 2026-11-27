<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Rates;

use AIArmada\Shipping\Features\Rates\Actions\CalculateShippingRate;
use AIArmada\Shipping\Features\Rates\Actions\WriteRateConfiguration;
use AIArmada\Shipping\Features\Rates\Api\ManageRateConfiguration;
use AIArmada\Shipping\Features\Rates\Api\CalculateRates;
use AIArmada\Shipping\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(CalculateRates::class, CalculateShippingRate::class);
        $app->bind(ManageRateConfiguration::class, WriteRateConfiguration::class);
    }

    public function boot(Application $app): void {}
}
