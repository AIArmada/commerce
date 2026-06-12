<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Contracts;

use AIArmada\Feedback\Models\FeedbackInvitation;

interface InvitationUrlGenerator
{
    public function generate(FeedbackInvitation $invitation, string $rawToken): string;
}
