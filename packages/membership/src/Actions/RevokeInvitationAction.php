<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Membership\Models\MembershipInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class RevokeInvitationAction
{
    use AsAction;

    public function handle(MembershipInvitation $invitation, Model $actor): void
    {
        DB::transaction(function () use ($actor, $invitation): void {
            $guardedInvitation = OwnerWriteGuard::findOrFailForOwner(
                MembershipInvitation::class,
                (string) $invitation->getKey(),
            );

            $lockedInvitation = MembershipInvitation::query()
                ->lockForUpdate()
                ->whereKey($guardedInvitation->getKey())
                ->firstOrFail();

            $lockedInvitation->update([
                'revoked_at' => CarbonImmutable::now(),
                'revoked_by' => $actor->getKey(),
            ]);
        });
    }
}
