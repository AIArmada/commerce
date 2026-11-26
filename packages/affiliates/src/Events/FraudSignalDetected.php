<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalReference;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class FraudSignalDetected implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly FraudSignalReference $signal
    ) {}
}
