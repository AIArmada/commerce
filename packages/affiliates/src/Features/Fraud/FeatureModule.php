<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud;

use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalReader;
use AIArmada\Affiliates\Features\Fraud\Queries\FraudSignalQuery;
use AIArmada\Affiliates\Features\Fraud\Actions\AnalyzeFraud;
use AIArmada\Affiliates\Features\Fraud\Actions\ReviewFraudSignal;
use AIArmada\Affiliates\Features\Fraud\Api\FraudApi;
use AIArmada\Affiliates\Features\Fraud\Api\FraudReviewApi;
use AIArmada\Affiliates\Platform\ExtensionRegistry;
use AIArmada\Affiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(FraudApi::class, AnalyzeFraud::class);
        $app->bind(FraudReviewApi::class, ReviewFraudSignal::class);
        $app->bind(FraudSignalReader::class, FraudSignalQuery::class);
        $app->tag($app->make(ExtensionRegistry::class)->classesFor('affiliates.fraud_rule'), 'affiliates.fraud_rule');
    }

    public function boot(Application $app): void {}
}
