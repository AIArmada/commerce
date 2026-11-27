<?php

declare(strict_types=1);

namespace AIArmada\Chip\Features\Payouts;

use AIArmada\Chip\Features\Payouts\Actions\CreateBankAccount;
use AIArmada\Chip\Features\Payouts\Actions\CreateSendInstruction;
use AIArmada\Chip\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(CreateBankAccount::class);
        $app->bind(CreateSendInstruction::class);
    }

    public function boot(Application $app): void {}
}
