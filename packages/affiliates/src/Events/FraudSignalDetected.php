<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class FraudSignalDetected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AffiliateFraudSignal $signal
    ) {}
}
