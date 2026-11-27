<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Statements;

use AIArmada\Chip\Features\Statements\Actions\AccountApi;
use AIArmada\Chip\Features\Statements\Api\AccountOperations;
use AIArmada\Chip\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(AccountOperations::class, AccountApi::class);
    }

    public function boot(Application $app): void {}
}
