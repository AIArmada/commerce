<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\Occurrence;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OccurrenceCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Occurrence $occurrence,
    ) {}
}
