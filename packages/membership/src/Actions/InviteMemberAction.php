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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

final class InviteMemberAction
{
    private const string UNIQUE_INDEX = 'membership_invitations_subject_email_role_status_unique';

    use AsAction;

    public function handle(Model $subject, string $email, MemberRole $role, Model $inviter, ?CarbonInterface $expiresAt = null): MembershipInvitation
    {
        app(MembershipSubjectGuard::class)->validate($subject);

        $tokenLength = max(32, (int) config('membership.invitations.token_length', 64));
        $email = mb_strtolower(mb_trim($email));
        $token = null;

        try {
            $invitation = DB::transaction(function () use ($email, $expiresAt, $inviter, $role, $subject, $tokenLength, &$token): MembershipInvitation {
                $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();

                $existing = $this->pendingInvitationQuery($subject, $email, $role)
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
        } catch (QueryException $exception) {
            if (! $this->isInvitationUniquenessViolation($exception)) {
                throw $exception;
            }

            $token = null;
            $invitation = $this->pendingInvitationQuery($subject, $email, $role)->firstOrFail();
        }

        if ($token !== null) {
            MembershipInvitationSent::dispatch($invitation, $token);
        }

        return $invitation;
    }

    /**
     * @return Builder<MembershipInvitation>
     */
    private function pendingInvitationQuery(Model $subject, string $email, MemberRole $role): Builder
    {
        return MembershipInvitation::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('email', $email)
            ->where('role', $role->spatieRoleName())
            ->where('status', InvitationStatus::Pending);
    }

    private function isInvitationUniquenessViolation(QueryException $exception): bool
    {
        if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
            return false;
        }

        $message = mb_strtolower($exception->getMessage());

        if (str_contains($message, self::UNIQUE_INDEX)) {
            return true;
        }

        $tableName = mb_strtolower((new MembershipInvitation)->getTable());

        return str_contains($message, $tableName)
            && str_contains($message, 'subject_type')
            && str_contains($message, 'subject_id')
            && str_contains($message, 'email')
            && str_contains($message, 'role')
            && str_contains($message, 'status');
    }
}
