<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Queries;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateUpline;
use AIArmada\Affiliates\Features\Upline\Api\UplineMember;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use AIArmada\Affiliates\Support\RevenueVolume;
use Carbon\CarbonImmutable;

final class UplineQuery
{
    /** @return list<UplineMember> */
    public function ancestors(string $affiliateId): array
    {
        $affiliate = Affiliate::query()->findOrFail($affiliateId);
        if (! config('affiliates.upline.enabled', false) || ! $affiliate->isCommercialPurpose()) {
            return [];
        }
        return AffiliateUpline::query()->where('descendant_id', $affiliateId)->where('depth', '>', 0)
            ->whereHas('ancestor', static fn ($q) => $q->whereCommercialPurpose())->with('ancestor')->orderBy('depth')->orderBy('ancestor_id')->get()
            ->map(static fn (AffiliateUpline $path): UplineMember => new UplineMember($path->ancestor_id, $path->ancestor->name, $path->ancestor->code, $path->depth))->all();
    }

    /** @return list<UplineMember> */
    public function descendants(string $affiliateId, ?int $depth = null): array
    {
        $affiliate = Affiliate::query()->findOrFail($affiliateId);
        if (! config('affiliates.upline.enabled', false) || ! $affiliate->isCommercialPurpose()) {
            return [];
        }
        return AffiliateUpline::query()->where('ancestor_id', $affiliateId)->where('depth', '>', 0)
            ->when($depth !== null, static fn ($q) => $q->where('depth', $depth))
            ->whereHas('descendant', static fn ($q) => $q->whereCommercialPurpose())->with('descendant')->orderBy('depth')->orderBy('descendant_id')->get()
            ->map(static fn (AffiliateUpline $path): UplineMember => new UplineMember($path->descendant_id, $path->descendant->name, $path->descendant->code, $path->depth))->all();
    }

    /** @return list<string> */
    public function descendantIds(string $affiliateId): array
    {
        $affiliate = Affiliate::query()->findOrFail($affiliateId);
        if (! config('affiliates.upline.enabled', false) || ! $affiliate->isCommercialPurpose()) {
            return [];
        }
        return AffiliateUpline::query()->where('ancestor_id', $affiliateId)->where('depth', '>', 0)
            ->whereHas('descendant', static fn ($q) => $q->whereCommercialPurpose())->pluck('descendant_id')->all();
    }

    public function descendantCount(string $affiliateId): int
    {
        $affiliate = Affiliate::query()->findOrFail($affiliateId);
        if (! config('affiliates.upline.enabled', false) || ! $affiliate->isCommercialPurpose()) {
            return 0;
        }
        return AffiliateUpline::query()->where('ancestor_id', $affiliateId)->where('depth', '>', 0)
            ->whereHas('descendant', static fn ($q) => $q->whereCommercialPurpose())->count();
    }

    /**
     * Get team sales for a given period.
     */
    public function teamSales(string $affiliateId, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, ?string $currency = null): int
    {
        $affiliate = Affiliate::query()->findOrFail($affiliateId);
        if (! $this->isEnabled() || ! $affiliate->isCommercialPurpose()) {
            return 0;
        }

        $descendantIds = AffiliateUpline::query()
            ->where('ancestor_id', $affiliate->getKey())
            ->where('depth', '>', 0)
            ->whereHas('descendant', static fn ($query) => $query->whereCommercialPurpose())
            ->select('descendant_id');

        $query = AffiliateConversion::query()
            ->whereIn('affiliate_id', $descendantIds);

        if ($from) {
            $query->where('occurred_at', '>=', $from);
        }

        if ($to) {
            $query->where('occurred_at', '<=', $to);
        }

        $rows = $query
            ->toBase()
            ->selectRaw('commission_currency as currency, COALESCE(SUM(COALESCE(value_minor, 0)), 0) as total')
            ->groupBy('commission_currency')
            ->get();

        $reference = RevenueVolume::referenceFor($affiliate);

        return RevenueVolume::measurableIn(RevenueVolume::foldRows($rows, $reference), $currency ?? $reference, $to);
    }

    /**
     * Count active downlines.
     */
    public function activeDownlineCount(string $affiliateId): int
    {
        $affiliate = Affiliate::query()->findOrFail($affiliateId);
        if (! $this->isEnabled() || ! $affiliate->isCommercialPurpose()) {
            return 0;
        }

        $descendantIds = AffiliateUpline::query()
            ->where('ancestor_id', $affiliate->getKey())
            ->where('depth', '>', 0)
            ->whereHas('descendant', static fn ($query) => $query->whereCommercialPurpose())
            ->select('descendant_id');

        return Affiliate::query()
            ->whereIn('id', $descendantIds)
            ->whereCommercialPurpose()
            ->where('status', AffiliateStatus::normalize(Active::class))
            ->count();
    }

    /**
     * Build a tree structure for visualization.
     *
     * @return array<string, mixed>
     */
    public function tree(string $affiliateId, int $maxDepth = 5): array
    {
        $root = Affiliate::query()->with('rank')->findOrFail($affiliateId);
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

        $children = Affiliate::query()->whereIn('id', AffiliateUpline::query()->where('ancestor_id', $parent->getKey())->where('depth', 1)->select('descendant_id'))->whereCommercialPurpose()->with('rank')->orderBy('id')->get();

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

}
