<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

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
        if (! $invitation->isValid()) {
            throw new RuntimeException('Invitation is no longer valid.');
        }

        $userEmail = method_exists($user, 'getEmailForVerification')
            ? $user->getEmailForVerification()
            : $user->getAttribute('email');

        if (! is_string($userEmail) || mb_strtolower($userEmail) !== $invitation->email) {
            throw new RuntimeException('Invitation email does not match the accepting user.');
        }

        $role = MemberRole::fromSpatieRoleName($invitation->role);

        if ($role === null) {
            throw new RuntimeException('Invitation contains an invalid membership role.');
        }

        DB::transaction(function () use ($invitation, $role, $user): void {
            $invitation->update([
                'accepted_at' => now(),
                'accepted_by' => $user->getKey(),
            ]);

            AddMemberAction::make()->handle(
                $invitation->subject,
                $user,
                $role,
            );
        });

        MembershipInvitationAccepted::dispatch($invitation->fresh());
    }
}
