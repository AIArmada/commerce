<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class FraudSignalDetected implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AffiliateFraudSignal $signal
    ) {}
}
