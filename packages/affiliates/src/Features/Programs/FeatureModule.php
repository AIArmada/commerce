<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs;

use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->scoped(Api\MerchantCatalog::class, Queries\ProgramCatalogQuery::class);
    }

    public function boot(Application $app): void {}
}
