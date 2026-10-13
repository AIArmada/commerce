<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Actions;

use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Events\ReferralInviteAccepted;
use AIArmada\Referrals\Exceptions\ReferralInviteNotFoundException;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Support\ReferralContactNormalizer;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

final class AcceptReferralInvite
{
    use AsAction;

    public function handle(string $token, Model $invitee, string $inviteeContact): ReferralInvite
    {
        if (! $invitee->exists || $invitee->getKey() === null) {
            throw new LogicException('A persisted invitee model is required.');
        }

        $contactHash = app(ReferralContactNormalizer::class)->hash($inviteeContact);
        $invite = DB::transaction(function () use ($token, $invitee, $contactHash): ReferralInvite {
            $guard = app(ReferralProgramGuard::class);
            $scopedInvite = $guard->inviteByToken($token);
            $guard->program((string) $scopedInvite->program_id, true);
            $lockedInvite = ReferralInvite::query()
                ->where('program_id', $scopedInvite->program_id)
                ->whereKey($scopedInvite->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvite->status !== ReferralInviteStatus::Pending
                || $lockedInvite->isExpiredAt(CarbonImmutable::now())
                || ($lockedInvite->referrer_type === $invitee->getMorphClass()
                    && (string) $lockedInvite->referrer_id === (string) $invitee->getKey())
                || ! hash_equals((string) $lockedInvite->invitee_hash, $contactHash)) {
                throw ReferralInviteNotFoundException::invalid();
            }

            $lockedInvite->forceFill([
                'accepted_by_type' => $invitee->getMorphClass(),
                'accepted_by_id' => (string) $invitee->getKey(),
                'status' => ReferralInviteStatus::Accepted,
                'accepted_at' => CarbonImmutable::now(),
            ]);
            $lockedInvite->save();

            return $lockedInvite;
        });

        ReferralInviteAccepted::dispatch($invite->fresh());

        return $invite->fresh();
    }
}
