<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Events\MembershipInvitationSent;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Support\MembershipSubjectGuard;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

final class InviteMemberAction
{
    use AsAction;

    public function handle(Model $subject, string $email, MemberRole $role, Model $inviter, ?CarbonInterface $expiresAt = null): MembershipInvitation
    {
        app(MembershipSubjectGuard::class)->validate($subject);

        $tokenLength = max(32, (int) config('membership.invitations.token_length', 64));
        $token = Str::random($tokenLength);

        $invitation = new MembershipInvitation;
        $invitation->fill([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'email' => mb_strtolower($email),
            'role' => $role->spatieRoleName(),
            'invited_by' => $inviter->getKey(),
        ]);
        $invitation->issue(
            $token,
            $expiresAt ?? CarbonImmutable::now()->addDays(
                (int) config('membership.invitations.default_expiry_days', 14)
            ),
        );
        $invitation->save();

        MembershipInvitationSent::dispatch($invitation, $token);

        return $invitation;
    }
}
