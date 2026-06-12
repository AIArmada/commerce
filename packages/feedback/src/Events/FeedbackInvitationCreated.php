<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Events;

use AIArmada\Feedback\Models\FeedbackInvitation;
use Illuminate\Foundation\Events\Dispatchable;

final class FeedbackInvitationCreated
{
    use Dispatchable;

    public function __construct(
        public FeedbackInvitation $invitation,
    ) {}
}
