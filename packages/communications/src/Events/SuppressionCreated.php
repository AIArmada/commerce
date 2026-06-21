<?php

declare(strict_types=1);

namespace AIArmada\Communications\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SuppressionCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $suppressionId,
        public readonly string $reason,
        public readonly string $destinationHash,
    ) {}
}
