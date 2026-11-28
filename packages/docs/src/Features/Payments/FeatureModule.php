<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Payments;

use AIArmada\Docs\Contracts\DocumentPaymentEditor;
use AIArmada\Docs\Contracts\DocumentPaymentRecorder;
use AIArmada\Docs\Features\Payments\Actions\EditDocPayment;
use AIArmada\Docs\Features\Payments\Actions\RecordDocPayment;
use AIArmada\Docs\Features\Payments\Queries\PaymentRelationModelResolver;
use AIArmada\Docs\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Docs\Runtime\PaymentRelationModel;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(DocumentPaymentRecorder::class, RecordDocPayment::class);
        $app->bind(DocumentPaymentEditor::class, EditDocPayment::class);
        $app->bind(PaymentRelationModel::class, PaymentRelationModelResolver::class);
    }

    public function boot(Application $app): void {}
}
