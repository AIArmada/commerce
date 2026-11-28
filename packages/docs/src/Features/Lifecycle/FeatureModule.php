<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Lifecycle;

use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\Contracts\DocumentLifecycle;
use AIArmada\Docs\Contracts\DocumentPdfCleanup;
use AIArmada\Docs\Contracts\DocumentPdfPublisher;
use AIArmada\Docs\Features\Lifecycle\Actions\DeleteStoredDocumentPdf;
use AIArmada\Docs\Features\Lifecycle\Actions\DocumentOperations;
use AIArmada\Docs\Features\Lifecycle\Actions\ManageDocumentRecords;
use AIArmada\Docs\Features\Lifecycle\Actions\ManageDocuments;
use AIArmada\Docs\Features\Lifecycle\Actions\StoreDocumentPdf;
use AIArmada\Docs\Features\Lifecycle\Queries\LifecycleRelationModelsResolver;
use AIArmada\Docs\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Docs\Runtime\DocumentRecords;
use AIArmada\Docs\Runtime\LifecycleRelationModels;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->scoped(DocumentRecords::class, ManageDocumentRecords::class);
        $app->bind(DocumentLifecycle::class, ManageDocuments::class);
        $app->bind(DocumentPdfPublisher::class, StoreDocumentPdf::class);
        $app->bind(DocumentPdfCleanup::class, DeleteStoredDocumentPdf::class);
        $app->bind(DocServiceInterface::class, DocumentOperations::class);
        $app->bind(LifecycleRelationModels::class, LifecycleRelationModelsResolver::class);
    }

    public function boot(Application $app): void {}
}
