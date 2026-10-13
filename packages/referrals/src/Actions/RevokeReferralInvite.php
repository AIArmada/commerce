<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Actions;

use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

final class RevokeReferralInvite
{
    use AsAction;

    public function handle(ReferralInvite | string $invite, ?string $reason = null): ReferralInvite
    {
        $inviteId = $invite instanceof ReferralInvite ? (string) $invite->getKey() : $invite;

        $revoked = DB::transaction(function () use ($inviteId, $reason): ReferralInvite {
            $guard = app(ReferralProgramGuard::class);
            $scopedInvite = $guard->invite($inviteId);
            $guard->program((string) $scopedInvite->program_id, true);
            $lockedInvite = ReferralInvite::query()
                ->where('program_id', $scopedInvite->program_id)
                ->whereKey($inviteId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedInvite->status, [ReferralInviteStatus::Pending, ReferralInviteStatus::Accepted], true)) {
                throw new LogicException('Only pending or accepted referral invites can be revoked.');
            }

            $metadata = is_array($lockedInvite->metadata) ? $lockedInvite->metadata : [];

            if ($reason !== null && mb_trim($reason) !== '') {
                $metadata['revocation_reason'] = mb_trim($reason);
            }

            $lockedInvite->metadata = $metadata;
            $lockedInvite->status = ReferralInviteStatus::Revoked;
            $lockedInvite->revoked_at = CarbonImmutable::now();
            $lockedInvite->save();

            return $lockedInvite;
        });

        return $revoked->fresh();
    }
}
