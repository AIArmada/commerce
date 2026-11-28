<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes;

use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Features\Passes\Persistence\PassTransferWriter;
use AIArmada\Ticketing\Features\Passes\Policies\PassTransferPolicy;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(PassTransferServiceInterface::class, PassTransferWriter::class);
    }

    public function boot(Application $app): void
    {
        Gate::policy(Pass::class, PassTransferPolicy::class);
    }
}
