<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Support\ReferralDatabase;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

final readonly class ExpireReferralInvites
{
    public function handle(CarbonImmutable $now): int
    {
        if (! (bool) config('referrals.owner.enabled', true)) {
            return $this->expireCurrentOwner($now);
        }

        $typeColumn = (string) config('referrals.owner.owner_type_column', 'owner_type');
        $idColumn = (string) config('referrals.owner.owner_id_column', 'owner_id');
        $pageSize = 500;
        $offset = 0;
        $expiredCount = 0;

        // Global visibility is read-only outside an explicit global pass.
        do {
            $ownerTuples = ReferralProgram::query()
                ->withoutOwnerScope()
                ->select([$typeColumn . ' as owner_type', $idColumn . ' as owner_id'])
                ->distinct()
                ->orderBy($typeColumn)
                ->orderBy($idColumn)
                ->offset($offset)
                ->limit($pageSize)
                ->get();

            $expiredCount += OwnerScopeOverride::withoutIncludeGlobal(
                fn (): int => $this->expireEveryOwner($ownerTuples, $now),
            );

            $offset += $pageSize;
        } while ($ownerTuples->count() === $pageSize);

        return $expiredCount;
    }

    /**
     * @param  iterable<ReferralProgram>  $ownerTuples
     */
    private function expireEveryOwner(iterable $ownerTuples, CarbonImmutable $now): int
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
                report(new RuntimeException(sprintf('Skipped referral programs with invalid owner [%s:%s].', (string) $ownerType, (string) $ownerId)));

                continue;
            }

            try {
                $owner = OwnerContext::fromTypeAndIdOrFail($ownerType, $ownerId);
            } catch (Throwable) {
                report(new RuntimeException(sprintf('Skipped referral programs with unresolved owner [%s:%s].', $ownerType, (string) $ownerId)));

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
        // Programs define the tenant boundary for invites.
        $dueInvites = ReferralInvite::query()
            ->whereIn('program_id', ReferralProgram::query()->select('id'))
            ->whereIn('status', [ReferralInviteStatus::Pending->value, ReferralInviteStatus::Accepted->value])
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', $now)
            ->lazyById(500);

        $count = 0;

        foreach ($dueInvites as $dueInvite) {
            $count += ReferralDatabase::connection()->transaction(function () use ($dueInvite, $now): int {
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
