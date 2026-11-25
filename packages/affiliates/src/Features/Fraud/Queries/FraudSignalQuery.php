<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Queries;

use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalReader;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalReference;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalSnapshot;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;

final class FraudSignalQuery implements FraudSignalReader
{
    public function findUnscoped(string $signalId): ?FraudSignalSnapshot
    {
        $signal = AffiliateFraudSignal::query()->withoutGlobalScope('affiliate_owner')->find($signalId);

        if (! $signal instanceof AffiliateFraudSignal) {
            return null;
        }

        return new FraudSignalSnapshot(
            new FraudSignalReference((string) $signal->getKey(), (string) $signal->affiliate_id, $signal->conversion_id, $signal->touchpoint_id),
            $signal->rule_code,
            $signal->severity,
            (int) $signal->risk_points,
            $signal->detected_at?->toImmutable(),
        );
    }
}
