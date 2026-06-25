<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Models\MembershipInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class RevokeInvitationAction
{
    use AsAction;

    public function handle(MembershipInvitation $invitation, Model $actor): void
    {
        DB::transaction(function () use ($actor, $invitation): void {
            $lockedInvitation = MembershipInvitation::query()
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            $lockedInvitation->update([
                'revoked_at' => now(),
                'revoked_by' => $actor->getKey(),
            ]);
        });
    }
}
