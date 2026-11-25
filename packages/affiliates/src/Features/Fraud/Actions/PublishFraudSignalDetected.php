<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Actions;

use AIArmada\Affiliates\Events\FraudSignalDetected;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalReference;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;

final readonly class PublishFraudSignalDetected
{
    public function execute(AffiliateFraudSignal $signal): void
    {
        FraudSignalDetected::dispatch(new FraudSignalReference(
            (string) $signal->getKey(),
            (string) $signal->affiliate_id,
            $signal->conversion_id,
            $signal->touchpoint_id,
        ));
    }
}
