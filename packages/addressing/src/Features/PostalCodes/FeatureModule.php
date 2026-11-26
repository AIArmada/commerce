<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\PostalCodes;

use AIArmada\Addressing\Contracts\PostalCodeWriter;
use AIArmada\Addressing\Contracts\PostalCodeAdminImporter;
use AIArmada\Addressing\Contracts\PostalCodeImporter;
use AIArmada\Addressing\Contracts\SingaporePostalCodeResolver;
use AIArmada\Addressing\Features\PostalCodes\Actions\SavePostalCodeAction;
use AIArmada\Addressing\Features\PostalCodes\Actions\ImportPostalCodeAdminDataAction;
use AIArmada\Addressing\Features\PostalCodes\Actions\ImportPostalCodesAction;
use AIArmada\Addressing\Features\PostalCodes\Actions\ResolveSingaporePostalCodesAction;
use AIArmada\Addressing\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(PostalCodeWriter::class, SavePostalCodeAction::class);
        $app->singleton(PostalCodeAdminImporter::class, ImportPostalCodeAdminDataAction::class);
        $app->singleton(PostalCodeImporter::class, ImportPostalCodesAction::class);
        $app->singleton(SingaporePostalCodeResolver::class, ResolveSingaporePostalCodesAction::class);
    }

    public function boot(Application $app): void {}
}
