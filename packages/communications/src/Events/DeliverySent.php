<?php

declare(strict_types=1);

namespace AIArmada\Communications\Events;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DeliverySent implements ShouldQueue
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $deliveryId,
        public readonly string $communicationId,
        public readonly string $channel,
    ) {}
}
