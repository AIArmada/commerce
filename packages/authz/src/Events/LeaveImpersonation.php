<?php

declare(strict_types=1);

namespace AIArmada\Authz\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveImpersonation
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Authenticatable $impersonator,
        public readonly Authenticatable $impersonated
    ) {}
}
