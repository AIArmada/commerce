<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\MembershipStatus;
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
            ->with(['tiers', 'creatives'])
            ->get();
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
