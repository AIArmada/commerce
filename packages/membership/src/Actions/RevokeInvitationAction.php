<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Support\MembershipUserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class RevokeInvitationAction
{
    use AsAction;

    public function handle(MembershipInvitation $invitation, Model $actor): void
    {
        MembershipUserModel::assertPersisted($actor);

        DB::transaction(function () use ($actor, $invitation): void {
            $guardedInvitation = OwnerWriteGuard::findOrFailForOwner(
                MembershipInvitation::class,
                (string) $invitation->getKey(),
            );

            $lockedInvitation = MembershipInvitation::query()
                ->lockForUpdate()
                ->whereKey($guardedInvitation->getKey())
                ->firstOrFail();

            $lockedInvitation->transitionTo(InvitationStatus::Revoked, $actor)->save();
        });
    }
}
