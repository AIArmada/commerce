<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Support;

use AIArmada\Feedback\Contracts\InvitationUrlGenerator as InvitationUrlGeneratorContract;
use AIArmada\Feedback\Models\FeedbackInvitation;

final class InvitationUrlGenerator implements InvitationUrlGeneratorContract
{
    public function generate(FeedbackInvitation $invitation, string $rawToken): string
    {
        $prefix = (string) config('feedback.http.route_prefix', 'feedback');

        return url("/{$prefix}/invitations/{$rawToken}");
    }
}
