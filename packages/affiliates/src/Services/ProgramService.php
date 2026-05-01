<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Events\AffiliateProgramLeft;
use AIArmada\Affiliates\Events\AffiliateTierUpgraded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use RuntimeException;

final class ProgramService
{
    public function __construct(
        private readonly Dispatcher $events
    ) {}

    /**
     * Get all active and public programs.
     *
     * @return Collection<int, AffiliateProgram>
     */
    public function getAvailablePrograms(): Collection
    {
        return AffiliateProgram::query()
            ->active()
            ->public()
            ->with('tiers')
            ->get();
    }

    /**
     * Join an affiliate to a program.
     */
    public function joinProgram(Affiliate $affiliate, AffiliateProgram $program): AffiliateProgramMembership
    {
        $program = $this->resolveAccessibleProgram($program);

        // Check if already a member
        $existing = AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('program_id', $program->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $defaultTier = $this->resolveDefaultTier($program);

        $status = $program->requires_approval
            ? MembershipStatus::Pending
            : MembershipStatus::Approved;

        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $affiliate->id,
            'program_id' => $program->id,
            'tier_id' => $defaultTier?->id,
            'status' => $status,
            'applied_at' => now(),
            'approved_at' => $status === MembershipStatus::Approved ? now() : null,
        ]);

        if ($status === MembershipStatus::Approved) {
            $this->events->dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));
        }

        return $membership;
    }

    /**
     * Leave a program.
     */
    public function leaveProgram(Affiliate $affiliate, AffiliateProgram $program): void
    {
        $membership = AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('program_id', $program->id)
            ->first();

        if ($membership) {
            $membership->delete();
            $this->events->dispatch(new AffiliateProgramLeft($affiliate, $program));
        }
    }

    /**
     * Approve a pending membership.
     */
    public function approveMembership(AffiliateProgramMembership $membership, ?string $approvedBy = null): void
    {
        $membership->approve($approvedBy);

        $this->events->dispatch(new AffiliateProgramJoined(
            $membership->affiliate,
            $membership->program,
            $membership
        ));
    }

    /**
     * Upgrade an affiliate to a new tier within a program.
     */
    public function upgradeTier(
        Affiliate $affiliate,
        AffiliateProgram $program,
        AffiliateProgramTier $tier
    ): AffiliateProgramMembership {
        $program = $this->resolveAccessibleProgram($program);
        $tier = $this->resolveAccessibleTier($program, $tier);

        $membership = AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('program_id', $program->id)
            ->first();

        if (! $membership) {
            throw new RuntimeException('Affiliate is not a member of this program');
        }

        $oldTier = $membership->tier;
        $membership->upgradeTier($tier);

        $this->events->dispatch(new AffiliateTierUpgraded(
            $affiliate,
            $program,
            $oldTier,
            $tier
        ));

        return $membership;
    }

    /**
     * Process tier upgrades for all affiliates in a program.
     */
    public function processTierUpgrades(AffiliateProgram $program): int
    {
        $upgraded = 0;

        $memberships = AffiliateProgramMembership::query()
            ->where('program_id', $program->id)
            ->where('status', MembershipStatus::Approved)
            ->with(['affiliate', 'tier'])
            ->get();

        $tiers = $this->resolveProgramTiers($program);

        foreach ($memberships as $membership) {
            $bestTier = $this->findBestTier($membership->affiliate, $program, $tiers);

            if ($bestTier && $bestTier->id !== $membership->tier_id) {
                if (! $membership->tier || $bestTier->level < $membership->tier->level) {
                    $this->upgradeTier($membership->affiliate, $program, $bestTier);
                    $upgraded++;
                }
            }
        }

        return $upgraded;
    }

    /**
     * Get programs an affiliate belongs to.
     *
     * @return Collection<int, AffiliateProgram>
     */
    public function getAffiliatePrograms(Affiliate $affiliate): Collection
    {
        return $affiliate->programs ?? collect();
    }

    /**
     * Check if an affiliate is a member of a program.
     */
    public function isMember(Affiliate $affiliate, AffiliateProgram $program): bool
    {
        return AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('program_id', $program->id)
            ->where('status', MembershipStatus::Approved)
            ->exists();
    }

    /**
     * Get membership details for an affiliate in a program.
     */
    public function getMembership(Affiliate $affiliate, AffiliateProgram $program): ?AffiliateProgramMembership
    {
        return AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('program_id', $program->id)
            ->with(['tier'])
            ->first();
    }

    /**
     * @param  Collection<int, AffiliateProgramTier>  $tiers
     */
    private function findBestTier(
        Affiliate $affiliate,
        AffiliateProgram $program,
        Collection $tiers
    ): ?AffiliateProgramTier {
        return $tiers
            ->filter(fn (AffiliateProgramTier $tier) => $tier->meetsUpgradeRequirements($affiliate, $program))
            ->first();
    }

    private function resolveAccessibleProgram(AffiliateProgram $program): AffiliateProgram
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return $program;
        }

        /** @var AffiliateProgram $validatedProgram */
        $validatedProgram = OwnerWriteGuard::findOrFailForOwner(
            AffiliateProgram::class,
            $program->getKey(),
            includeGlobal: true,
            message: 'Selected program is not accessible in the current owner scope.',
        );

        return $validatedProgram;
    }

    private function resolveAccessibleTier(AffiliateProgram $program, AffiliateProgramTier $tier): AffiliateProgramTier
    {
        $validatedTier = $program->tiers()
            ->withoutGlobalScope('program_owner')
            ->whereKey($tier->getKey())
            ->first();

        if (! $validatedTier instanceof AffiliateProgramTier) {
            throw new RuntimeException('Selected tier does not belong to the specified program.');
        }

        return $validatedTier;
    }

    private function resolveDefaultTier(AffiliateProgram $program): ?AffiliateProgramTier
    {
        return $this->resolveProgramTiers($program)->first();
    }

    /**
     * @return Collection<int, AffiliateProgramTier>
     */
    private function resolveProgramTiers(AffiliateProgram $program): Collection
    {
        /** @var Collection<int, AffiliateProgramTier> $tiers */
        $tiers = $program->tiers()
            ->withoutGlobalScope('program_owner')
            ->orderBy('level')
            ->get();

        return $tiers;
    }
}
