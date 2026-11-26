<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Actions;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Events\AffiliateTierUpgraded;
use AIArmada\Affiliates\Features\Programs\Api\MembershipRecord;
use AIArmada\Affiliates\Features\Programs\Api\ProgramTierManagement;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Features\Programs\Queries\ProgramMembershipQuery;
use AIArmada\Affiliates\Features\Programs\Queries\ProgramTierQualificationQuery;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use RuntimeException;

final readonly class UpgradeProgramTier implements ProgramTierManagement
{
    public function __construct(
        private ProgramMembershipQuery $memberships,
        private ProgramTierQualificationQuery $qualification,
        private Dispatcher $events,
    ) {}

    public function upgrade(string $affiliateId, string $programId, string $tierId): MembershipRecord
    {
        return (new AffiliateProgramMembership)->getConnection()->transaction(function () use ($affiliateId, $programId, $tierId): MembershipRecord {
            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
            $program = $this->resolveProgram($programId);
            $tier = AffiliateProgramTier::query()->where('program_id', $program->getKey())->orderBy('level')->withoutGlobalScope('program_owner')->whereKey($tierId)->first();

            if ($tier === null) {
                throw new RuntimeException('Selected tier does not belong to the specified program.');
            }

            $membership = AffiliateProgramMembership::query()
                ->where('affiliate_id', $affiliateId)
                ->where('program_id', $programId)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                throw new RuntimeException('Affiliate is not a member of this program');
            }

            $this->change($affiliate, $program, $membership, $tier);

            return $this->memberships->project($membership);
        }, attempts: 3);
    }

    public function processUpgrades(string $programId): int
    {
        $program = $this->resolveProgram($programId);
        $tiers = AffiliateProgramTier::query()->where('program_id', $program->getKey())->orderBy('level')->withoutGlobalScope('program_owner')->reorder('level', 'desc')->get();
        $upgraded = 0;

        AffiliateProgramMembership::query()
            ->where('program_id', $programId)
            ->where('status', MembershipStatus::Approved)
            ->select(['id', 'affiliate_id'])
            ->chunkById(100, function ($memberships) use ($program, $tiers, &$upgraded): void {
                foreach ($memberships as $membership) {
                    $upgraded += (int) $this->processMembership($membership->id, $membership->affiliate_id, $program, $tiers);
                }
            });

        return $upgraded;
    }

    /** @param Collection<int, AffiliateProgramTier> $tiers */
    private function processMembership(string $membershipId, string $affiliateId, AffiliateProgram $program, Collection $tiers): bool
    {
        return (new AffiliateProgramMembership)->getConnection()->transaction(function () use ($membershipId, $affiliateId, $program, $tiers): bool {
            $affiliate = Affiliate::query()->lockForUpdate()->find($affiliateId);
            $membership = AffiliateProgramMembership::query()
                ->where('program_id', $program->id)
                ->where('affiliate_id', $affiliateId)
                ->lockForUpdate()
                ->find($membershipId);

            if ($affiliate === null || $membership === null || ! $membership->isActive()) {
                return false;
            }

            $best = $this->qualification->best($affiliate, $program, $tiers);
            $current = $tiers->firstWhere('id', $membership->tier_id);

            if ($best === null || ($current !== null && $best->level <= $current->level)) {
                return false;
            }

            return $this->change($affiliate, $program, $membership, $best);
        }, attempts: 3);
    }

    private function change(Affiliate $affiliate, AffiliateProgram $program, AffiliateProgramMembership $membership, AffiliateProgramTier $tier): bool
    {
        if ($membership->tier_id === $tier->id) {
            return false;
        }

        $oldTierId = $membership->tier_id;
        $membership->update(['tier_id' => $tier->id]);

        $this->events->dispatch(new AffiliateTierUpgraded((string) $affiliate->getKey(), (string) $program->getKey(), $oldTierId, (string) $tier->getKey()));

        return true;
    }

    private function resolveProgram(string $programId): AffiliateProgram
    {
        return (bool) config('affiliates.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $programId, includeGlobal: true)
            : AffiliateProgram::query()->findOrFail($programId);
    }
}
