<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
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
        // Cross-tenant enumeration is this command's whole job: the scheduled
        // sweep must reach every owner's due invites, so it reads owner tuples
        // unscoped and then re-enters each owner's context (OwnerContext::withOwner)
        // for the actual expiry work, which stays owner-scoped.
        $ownerTuples = ReferralProgram::query()
            ->withoutOwnerScope()
            ->select([$typeColumn . ' as owner_type', $idColumn . ' as owner_id'])
            ->distinct()
            ->get();

        /** @var Collection<int, ReferralProgram> $ownerTuples */
        // Each per-owner pass is a WRITE pass, and `include_global` is read
        // visibility only: a host that opts into seeing global programs must
        // not have the sweep reach for their invites, because the guard
        // resolves writes with includeGlobal=false and would refuse the row
        // mid-sweep. Suppressing the override for the whole iteration keeps
        // every pass on rows it can actually write, and removes any
        // dependence on the order the owner tuples happen to come back in.
        // Mirrors OwnerBatchRunner::collectForOwners().
        $expiredCount = OwnerScopeOverride::withoutIncludeGlobal(
            fn (): int => $this->expireEveryOwner($ownerTuples, $now),
        );

        $this->info(sprintf('Expired %d referral invite(s).', $expiredCount));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, ReferralProgram>  $ownerTuples
     */
    private function expireEveryOwner(Collection $ownerTuples, CarbonImmutable $now): int
    {
        $expiredCount = 0;

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

        return $expiredCount;
    }

    private function expireCurrentOwner(CarbonImmutable $now): int
    {
        // Invites are not owner-scoped rows themselves: the program is the
        // tenant boundary. Constrain the due scan to programs visible in
        // THIS owner context (OwnerScope applies under the per-owner
        // withOwner() entered above, including explicit-global). Each invite
        // is re-resolved and locked within its own transaction below.
        $dueInvites = ReferralInvite::query()
            ->whereIn('program_id', ReferralProgram::query()->select('id'))
            ->whereIn('status', [ReferralInviteStatus::Pending->value, ReferralInviteStatus::Accepted->value])
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', $now)
            ->orderBy('id')
            ->get();

        $count = 0;

        // Each invite expires in its own transaction so one bad row cannot
        // roll back the owner's whole batch.
        foreach ($dueInvites as $dueInvite) {
            $count += DB::transaction(function () use ($dueInvite, $now): int {
                $guard = app(ReferralProgramGuard::class);
                $invite = $guard->invite((string) $dueInvite->getKey(), true);

                if (! in_array($invite->status, [ReferralInviteStatus::Pending, ReferralInviteStatus::Accepted], true)
                    || $invite->expired_at === null
                    || $invite->expired_at->greaterThan($now)) {
                    return 0;
                }

                $invite->status = ReferralInviteStatus::Expired;
                $invite->save();

                return 1;
            });
        }

        return $count;
    }
}
