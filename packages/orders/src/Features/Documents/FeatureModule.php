<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Documents;

use AIArmada\Orders\Contracts\OrderDocuments;
use AIArmada\Orders\Features\Documents\Actions\GenerateInvoice;
use AIArmada\Orders\Features\Documents\Actions\IssueOrderDocument;
use AIArmada\Orders\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Orders\Runtime\OrderInvoiceDownload;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(OrderDocuments::class, IssueOrderDocument::class);
        $app->bind(OrderInvoiceDownload::class, GenerateInvoice::class);
    }

    public function boot(Application $app): void {}
}
