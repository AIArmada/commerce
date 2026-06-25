<?php

declare(strict_types=1);

namespace AIArmada\Membership\Events;

use AIArmada\Membership\Models\MembershipApplication;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MembershipApplicationCancelled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MembershipApplication $application,
    ) {}
}
