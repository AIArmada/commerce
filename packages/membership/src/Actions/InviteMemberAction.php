<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Enums\InvitationStatus;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Events\MembershipInvitationSent;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Support\MembershipSubjectGuard;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

final class InviteMemberAction
{
    use AsAction;

    public function handle(Model $subject, string $email, MemberRole $role, Model $inviter, ?CarbonInterface $expiresAt = null): MembershipInvitation
    {
        app(MembershipSubjectGuard::class)->validate($subject);

        $tokenLength = max(32, (int) config('membership.invitations.token_length', 64));
        $email = mb_strtolower(mb_trim($email));
        $token = null;

        $invitation = DB::transaction(function () use ($email, $expiresAt, $inviter, $role, $subject, $tokenLength, &$token): MembershipInvitation {
            $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();

            $existing = MembershipInvitation::query()
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey())
                ->where('email', $email)
                ->where('role', $role->spatieRoleName())
                ->where('status', InvitationStatus::Pending)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof MembershipInvitation) {
                return $existing;
            }

            $token = Str::random($tokenLength);
            $invitation = new MembershipInvitation;
            $invitation->fill([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'email' => $email,
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

            return $invitation;
        });

        if ($token !== null) {
            MembershipInvitationSent::dispatch($invitation, $token);
        }

        return $invitation;
    }
}
