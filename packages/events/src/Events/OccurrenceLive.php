<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\Occurrence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OccurrenceLive
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Occurrence $occurrence,
        public readonly ?Model $actor = null,
    ) {}
}
