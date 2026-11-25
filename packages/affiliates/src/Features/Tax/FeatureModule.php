<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Tax;

use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Affiliates\Contracts\TaxRecipientProvider;
use AIArmada\Affiliates\Contracts\TaxWorksheetWriter;
use AIArmada\Affiliates\Drivers\Tax\UnavailableTaxRecipientProvider;
use AIArmada\Affiliates\Drivers\Tax\PrivateTaxWorksheetWriter;
use AIArmada\Affiliates\Features\Tax\Api\TaxDocumentApi;
use AIArmada\Affiliates\Features\Tax\Queries\TaxDocumentGateway;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bindIf(TaxRecipientProvider::class, UnavailableTaxRecipientProvider::class);
        $app->bindIf(TaxWorksheetWriter::class, PrivateTaxWorksheetWriter::class);
        $app->bind(TaxDocumentApi::class, TaxDocumentGateway::class);
    }

    public function boot(Application $app): void {}
}
