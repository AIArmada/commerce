<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Events\MembershipInvitationAccepted;
use AIArmada\Membership\Models\MembershipInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class AcceptInvitationAction
{
    use AsAction;

    public function handle(MembershipInvitation $invitation, Model $user): void
    {
        $userEmail = method_exists($user, 'getEmailForVerification')
            ? $user->getEmailForVerification()
            : $user->getAttribute('email');

        $acceptedInvitation = DB::transaction(function () use ($invitation, $user, $userEmail): MembershipInvitation {
            $guardedInvitation = OwnerWriteGuard::findOrFailForOwner(
                MembershipInvitation::class,
                (string) $invitation->getKey(),
            );

            $lockedInvitation = MembershipInvitation::query()
                ->lockForUpdate()
                ->whereKey($guardedInvitation->getKey())
                ->firstOrFail();

            if (! $lockedInvitation->isValid()) {
                throw new RuntimeException('Invitation is no longer valid.');
            }

            if (! is_string($userEmail) || mb_strtolower($userEmail) !== $lockedInvitation->email) {
                throw new RuntimeException('Invitation email does not match the accepting user.');
            }

            $role = MemberRole::fromSpatieRoleName($lockedInvitation->role);

            if ($role === null) {
                throw new RuntimeException('Invitation contains an invalid membership role.');
            }

            $lockedInvitation->transitionStatus(InvitationStatus::Accepted, $user);

            AddMemberAction::make()->handle(
                $lockedInvitation->subject,
                $user,
                $role,
            );

            return $lockedInvitation;
        });

        MembershipInvitationAccepted::dispatch($acceptedInvitation->fresh());
    }
}
