<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateUpline;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class UplineService
{
    /**
     * Add an affiliate to the upline under a sponsor.
     */
    public function addToUpline(Affiliate $affiliate, ?Affiliate $sponsor = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        DB::transaction(function () use ($affiliate, $sponsor): void {
            AffiliateUpline::addToUpline($affiliate, $sponsor);

            $this->updateUplineCounts($affiliate);

            if ($sponsor) {
                $this->updateUplineCounts($sponsor);
            }
        });
    }

    /**
     * Remove an affiliate from the upline.
     */
    public function removeFromUpline(Affiliate $affiliate): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        AffiliateUpline::removeFromUpline($affiliate);
    }

    /**
     * Move an affiliate to a new sponsor.
     */
    public function changeSponsor(Affiliate $affiliate, Affiliate $newSponsor): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        DB::transaction(function () use ($affiliate, $newSponsor): void {
            AffiliateUpline::moveToNewSponsor($affiliate, $newSponsor);

            $this->updateUplineCounts($affiliate);
            $this->updateUplineCounts($newSponsor);
        });
    }

    /**
     * Get all upline affiliates (ancestors).
     *
     * @return Collection<int, Affiliate>
     */
    public function getUpline(Affiliate $affiliate): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        return AffiliateUpline::getAncestors($affiliate);
    }

    /**
     * Get all downline affiliates (descendants).
     *
     * @return Collection<int, Affiliate>
     */
    public function getDownline(Affiliate $affiliate): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        return AffiliateUpline::getDescendants($affiliate);
    }

    /**
     * Get direct recruits (level 1 downline).
     *
     * @return Collection<int, Affiliate>
     */
    public function getDirectRecruits(Affiliate $affiliate): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        return AffiliateUpline::getDirectChildren($affiliate);
    }

    /**
     * Get team sales for a given period.
     */
    public function getTeamSales(Affiliate $affiliate, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $descendantIds = AffiliateUpline::query()
            ->where('ancestor_id', $affiliate->getKey())
            ->where('depth', '>', 0)
            ->pluck('descendant_id');

        if ($descendantIds->isEmpty()) {
            return 0;
        }

        $query = AffiliateConversion::query()
            ->whereIn('affiliate_id', $descendantIds);

        if ($from) {
            $query->where('occurred_at', '>=', $from);
        }

        if ($to) {
            $query->where('occurred_at', '<=', $to);
        }

        return (int) $query->sum(DB::raw('COALESCE(value_minor, 0)'));
    }

    /**
     * Count active downlines.
     */
    public function getActiveDownlineCount(Affiliate $affiliate): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $descendantIds = AffiliateUpline::query()
            ->where('ancestor_id', $affiliate->getKey())
            ->where('depth', '>', 0)
            ->pluck('descendant_id');

        return Affiliate::query()
            ->whereIn('id', $descendantIds)
            ->where('status', AffiliateStatus::normalize(Active::class))
            ->count();
    }

    /**
     * Build a tree structure for visualization.
     *
     * @return array<string, mixed>
     */
    public function buildTree(Affiliate $root, int $maxDepth = 5): array
    {
        if (! $this->isEnabled()) {
            return [
                'id' => $root->id,
                'name' => $root->name,
                'code' => $root->code,
                'rank' => $root->rank?->name,
                'status' => AffiliateStatus::normalize($root->status),
                'stats' => [
                    'direct_recruits' => $root->direct_downline_count,
                    'total_downline' => $root->total_downline_count,
                ],
                'children' => [],
            ];
        }

        $maxDepth = $this->capMaxDepth($maxDepth);

        return [
            'id' => $root->id,
            'name' => $root->name,
            'code' => $root->code,
            'rank' => $root->rank?->name,
            'status' => AffiliateStatus::normalize($root->status),
            'stats' => [
                'direct_recruits' => $root->direct_downline_count,
                'total_downline' => $root->total_downline_count,
            ],
            'children' => $this->buildChildren($root, 1, $maxDepth),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildChildren(Affiliate $parent, int $currentDepth, int $maxDepth): array
    {
        if ($currentDepth > $maxDepth) {
            return [];
        }

        $children = AffiliateUpline::getDirectChildren($parent);

        return $children->map(function (Affiliate $child) use ($currentDepth, $maxDepth) {
            return [
                'id' => $child->id,
                'name' => $child->name,
                'code' => $child->code,
                'rank' => $child->rank?->name,
                'status' => AffiliateStatus::normalize($child->status),
                'stats' => [
                    'direct_recruits' => $child->direct_downline_count,
                    'total_downline' => $child->total_downline_count,
                ],
                'children' => $this->buildChildren($child, $currentDepth + 1, $maxDepth),
            ];
        })->all();
    }

    private function isEnabled(): bool
    {
        return (bool) config('affiliates.upline.enabled', false);
    }

    private function capMaxDepth(int $maxDepth): int
    {
        $configuredMaxDepth = (int) config('affiliates.upline.max_depth', 0);

        if ($configuredMaxDepth > 0) {
            return min($maxDepth, $configuredMaxDepth);
        }

        return $maxDepth;
    }

    private function updateUplineCounts(Affiliate $affiliate): void
    {
        $directCount = AffiliateUpline::query()
            ->where('ancestor_id', $affiliate->getKey())
            ->where('depth', 1)
            ->count();

        $totalCount = AffiliateUpline::getDescendantCount($affiliate);

        $depth = AffiliateUpline::query()
            ->where('descendant_id', $affiliate->getKey())
            ->max('depth') ?? 0;

        $affiliate->update([
            'direct_downline_count' => $directCount,
            'total_downline_count' => $totalCount,
            'network_depth' => $depth,
        ]);
    }
}
