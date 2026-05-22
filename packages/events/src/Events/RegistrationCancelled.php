<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\Registration;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class RegistrationCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Registration $registration,
        public readonly ?string $reason = null,
    ) {}
}
