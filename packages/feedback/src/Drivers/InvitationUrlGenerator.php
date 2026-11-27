<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Drivers;

use AIArmada\Feedback\Contracts\InvitationUrlGenerator as InvitationUrlGeneratorContract;

final class InvitationUrlGenerator implements InvitationUrlGeneratorContract
{
    public function generate(string $invitationId, string $rawToken): string
    {
        $prefix = (string) config('feedback.http.route_prefix', 'feedback');

        return url("/{$prefix}/invitations/{$rawToken}");
    }
}
