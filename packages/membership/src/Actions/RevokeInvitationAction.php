<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Models\MembershipInvitation;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

final class RevokeInvitationAction
{
    use AsAction;

    public function handle(MembershipInvitation $invitation, Model $actor): void
    {
        $invitation->update([
            'revoked_at' => now(),
            'revoked_by' => $actor->getKey(),
        ]);
    }
}
