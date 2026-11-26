<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\ReferenceIntegrity;

use AIArmada\Addressing\Contracts\GoogleAddressReferenceSource;
use AIArmada\Addressing\Drivers\GoogleAddressReference\GoogleAddressReferenceClient;
use AIArmada\Addressing\Features\ReferenceIntegrity\Actions\CompareAddressingReferenceExportsAction;
use AIArmada\Addressing\Features\ReferenceIntegrity\Actions\DiffCldrSubdivisionsAction;
use AIArmada\Addressing\Features\ReferenceIntegrity\Actions\DiffGoogleAddressReferenceAction;
use AIArmada\Addressing\Features\ReferenceIntegrity\Actions\ExportAddressingReferencesAction;
use AIArmada\Addressing\Features\ReferenceIntegrity\Support\ReferenceExportFile;
use AIArmada\Addressing\Features\ReferenceIntegrity\Support\ReferenceExportSorter;
use AIArmada\Addressing\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(GoogleAddressReferenceSource::class, GoogleAddressReferenceClient::class);

        foreach ([
            DiffCldrSubdivisionsAction::class,
            DiffGoogleAddressReferenceAction::class,
            ReferenceExportFile::class,
            ReferenceExportSorter::class,
            CompareAddressingReferenceExportsAction::class,
            ExportAddressingReferencesAction::class,
        ] as $class) {
            $app->singleton($class);
        }
    }

    public function boot(Application $app): void {}
}
