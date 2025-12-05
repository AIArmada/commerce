<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateNetwork;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class NetworkService
{
    public function __construct(
        private readonly Dispatcher $events
    ) {}

    /**
     * Add an affiliate to the network under a sponsor.
     */
    public function addToNetwork(Affiliate $affiliate, ?Affiliate $sponsor = null): void
    {
        AffiliateNetwork::addToNetwork($affiliate, $sponsor);

        $this->updateNetworkCounts($affiliate);

        if ($sponsor) {
            $this->updateNetworkCounts($sponsor);
        }
    }

    /**
     * Remove an affiliate from the network.
     */
    public function removeFromNetwork(Affiliate $affiliate): void
    {
        AffiliateNetwork::removeFromNetwork($affiliate);
    }

    /**
     * Move an affiliate to a new sponsor.
     */
    public function changeSponso(Affiliate $affiliate, Affiliate $newSponsor): void
    {
        AffiliateNetwork::moveToNewSponsor($affiliate, $newSponsor);

        $this->updateNetworkCounts($affiliate);
        $this->updateNetworkCounts($newSponsor);
    }

    /**
     * Get all upline affiliates (ancestors).
     *
     * @return Collection<int, Affiliate>
     */
    public function getUpline(Affiliate $affiliate): Collection
    {
        return AffiliateNetwork::getAncestors($affiliate);
    }

    /**
     * Get all downline affiliates (descendants).
     *
     * @return Collection<int, Affiliate>
     */
    public function getDownline(Affiliate $affiliate): Collection
    {
        return AffiliateNetwork::getDescendants($affiliate);
    }

    /**
     * Get direct recruits (level 1 downline).
     *
     * @return Collection<int, Affiliate>
     */
    public function getDirectRecruits(Affiliate $affiliate): Collection
    {
        return AffiliateNetwork::getDirectChildren($affiliate);
    }

    /**
     * Get team sales for a given period.
     */
    public function getTeamSales(Affiliate $affiliate, ?Carbon $from = null, ?Carbon $to = null): int
    {
        $descendantIds = AffiliateNetwork::query()
            ->where('ancestor_id', $affiliate->getKey())
            ->where('depth', '>', 0)
            ->pluck('descendant_id');

        if ($descendantIds->isEmpty()) {
            return 0;
        }

        $query = Affiliate::query()
            ->whereIn('id', $descendantIds)
            ->withSum([
                'conversions' => function ($q) use ($from, $to): void {
                    if ($from) {
                        $q->where('occurred_at', '>=', $from);
                    }
                    if ($to) {
                        $q->where('occurred_at', '<=', $to);
                    }
                },
            ], 'total_minor');

        return (int) $query->get()->sum('conversions_sum_total_minor');
    }

    /**
     * Count active downlines.
     */
    public function getActiveDownlineCount(Affiliate $affiliate): int
    {
        $descendantIds = AffiliateNetwork::query()
            ->where('ancestor_id', $affiliate->getKey())
            ->where('depth', '>', 0)
            ->pluck('descendant_id');

        return Affiliate::query()
            ->whereIn('id', $descendantIds)
            ->where('status', 'active')
            ->count();
    }

    /**
     * Build a tree structure for visualization.
     *
     * @return array<string, mixed>
     */
    public function buildTree(Affiliate $root, int $maxDepth = 5): array
    {
        return [
            'id' => $root->id,
            'name' => $root->name,
            'code' => $root->code,
            'rank' => $root->rank?->name,
            'status' => $root->status->value,
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

        $children = AffiliateNetwork::getDirectChildren($parent);

        return $children->map(function (Affiliate $child) use ($currentDepth, $maxDepth) {
            return [
                'id' => $child->id,
                'name' => $child->name,
                'code' => $child->code,
                'rank' => $child->rank?->name,
                'status' => $child->status->value,
                'stats' => [
                    'direct_recruits' => $child->direct_downline_count,
                    'total_downline' => $child->total_downline_count,
                ],
                'children' => $this->buildChildren($child, $currentDepth + 1, $maxDepth),
            ];
        })->all();
    }

    private function updateNetworkCounts(Affiliate $affiliate): void
    {
        $directCount = AffiliateNetwork::query()
            ->where('ancestor_id', $affiliate->getKey())
            ->where('depth', 1)
            ->count();

        $totalCount = AffiliateNetwork::getDescendantCount($affiliate);

        $depth = AffiliateNetwork::query()
            ->where('descendant_id', $affiliate->getKey())
            ->max('depth') ?? 0;

        $affiliate->update([
            'direct_downline_count' => $directCount,
            'total_downline_count' => $totalCount,
            'network_depth' => $depth,
        ]);
    }
}
