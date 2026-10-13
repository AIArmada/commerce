<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ExpireReferralInvitesCommand extends Command
{
    protected $signature = 'referrals:expire-invites';

    protected $description = 'Expire pending and accepted referral invites past their deadline.';

    public function handle(): int
    {
        $expiredCount = 0;
        $now = CarbonImmutable::now();

        if (! (bool) config('referrals.owner.enabled', true)) {
            $expiredCount = $this->expireCurrentOwner($now);
            $this->info(sprintf('Expired %d referral invite(s).', $expiredCount));

            return self::SUCCESS;
        }

        $typeColumn = (string) config('referrals.owner.owner_type_column', 'owner_type');
        $idColumn = (string) config('referrals.owner.owner_id_column', 'owner_id');
        $ownerTuples = ReferralProgram::query()
            ->withoutOwnerScope()
            ->select([$typeColumn . ' as owner_type', $idColumn . ' as owner_id'])
            ->distinct()
            ->get();

        /** @var Collection<int, ReferralProgram> $ownerTuples */
        foreach ($ownerTuples as $tuple) {
            $ownerType = $tuple->getAttribute('owner_type');
            $ownerId = $tuple->getAttribute('owner_id');

            if ($ownerType === null && $ownerId === null) {
                $expiredCount += OwnerContext::withOwner(null, fn (): int => $this->expireCurrentOwner($now));

                continue;
            }

            if (! is_string($ownerType) || (! is_string($ownerId) && ! is_int($ownerId))) {
                $this->warn(sprintf('Skipped referral programs with invalid owner [%s:%s].', (string) $ownerType, (string) $ownerId));

                continue;
            }

            try {
                $owner = OwnerContext::fromTypeAndIdOrFail($ownerType, $ownerId);
            } catch (Throwable) {
                $this->warn(sprintf('Skipped referral programs with unresolved owner [%s:%s].', $ownerType, (string) $ownerId));

                continue;
            }

            if ($owner === null) {
                continue;
            }

            $expiredCount += OwnerContext::withOwner($owner, fn (): int => $this->expireCurrentOwner($now));
        }

        $this->info(sprintf('Expired %d referral invite(s).', $expiredCount));

        return self::SUCCESS;
    }

    private function expireCurrentOwner(CarbonImmutable $now): int
    {
        return DB::transaction(function () use ($now): int {
            $guard = app(ReferralProgramGuard::class);
            $dueInvites = ReferralInvite::query()
                ->whereIn('status', [ReferralInviteStatus::Pending->value, ReferralInviteStatus::Accepted->value])
                ->whereNotNull('expired_at')
                ->where('expired_at', '<=', $now)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $count = 0;

            foreach ($dueInvites as $dueInvite) {
                $invite = $guard->invite((string) $dueInvite->getKey(), true);

                if (! in_array($invite->status, [ReferralInviteStatus::Pending, ReferralInviteStatus::Accepted], true)
                    || $invite->expired_at === null
                    || $invite->expired_at->greaterThan($now)) {
                    continue;
                }

                $invite->status = ReferralInviteStatus::Expired;
                $invite->save();
                $count++;
            }

            return $count;
        });
    }
}
