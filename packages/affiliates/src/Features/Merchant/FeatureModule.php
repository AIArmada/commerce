<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Merchant;

use AIArmada\Affiliates\Contracts\NetworkPostbackGateway;
use AIArmada\Affiliates\Drivers\Network\HttpNetworkPostbackGateway;
use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bindIf(NetworkPostbackGateway::class, HttpNetworkPostbackGateway::class);
    }

    public function boot(Application $app): void {}
}
