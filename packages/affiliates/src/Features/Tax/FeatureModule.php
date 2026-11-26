<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Tax;

use AIArmada\Affiliates\Contracts\AffiliateRemovalGuard;
use AIArmada\Affiliates\Features\Tax\Api\TaxDocumentApi;
use AIArmada\Affiliates\Features\Tax\Queries\TaxDocumentGateway;
use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(TaxDocumentApi::class, TaxDocumentGateway::class);
        $app->tag([Queries\TaxAffiliateRemovalGuard::class], AffiliateRemovalGuard::class);
    }

    public function boot(Application $app): void {}
}
