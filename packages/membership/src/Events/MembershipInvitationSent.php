<?php

declare(strict_types=1);

namespace AIArmada\Membership\Events;

use AIArmada\Membership\Models\MembershipInvitation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MembershipInvitationSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MembershipInvitation $invitation,
        public string $token,
    ) {}
}
