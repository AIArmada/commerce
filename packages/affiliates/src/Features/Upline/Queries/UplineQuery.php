<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Queries;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionStatisticsReader;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateNetworkMember;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateNetworkReader;
use AIArmada\Affiliates\Features\Upline\Api\UplineMember;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRank;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateUpline;
use AIArmada\Affiliates\Support\RevenueVolume;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class UplineQuery
{
    public function __construct(
        private ConversionStatisticsReader $conversions,
        private AffiliateNetworkReader $identity,
        private UplinePathQuery $paths,
    ) {}

    /** @return list<UplineMember> */
    public function ancestors(string $affiliateId): array
    {
        return $this->members($affiliateId, 'descendant_id', 'ancestor_id');
    }

    /** @return list<UplineMember> */
    public function descendants(string $affiliateId, ?int $depth = null): array
    {
        return $this->members($affiliateId, 'ancestor_id', 'descendant_id', $depth);
    }

    /**
     * @param  'ancestor_id'|'descendant_id'  $source
     * @param  'ancestor_id'|'descendant_id'  $target
     * @return list<UplineMember>
     */
    private function members(string $id, string $source, string $target, ?int $depth = null): array
    {
        $affiliate = $this->identity->requireMember($id, $this->connectionIdentity());

        if (! $this->isEnabled() || ! $affiliate->commercial) {
            return [];
        }

        $paths = $this->paths->commercialEndpoint(
            AffiliateUpline::query()->forOwner()->where($source, $id)->where('depth', '>', 0),
            $target,
        )->when($depth !== null, static fn ($query) => $query->where('depth', $depth))
            ->orderBy('depth')->orderBy($target)->get();

        $members = $this->identity->members($paths->pluck($target)->all(), $this->connectionIdentity());
        $result = [];

        foreach ($paths as $path) {
            $member = $members[(string) $path->getAttribute($target)] ?? null;

            if ($member !== null && $member->commercial) {
                $result[] = new UplineMember($member->reference->id, $member->name, $member->code, $path->depth);
            }
        }

        return $result;
    }

    /** @return list<string> */
    public function descendantIds(string $affiliateId): array
    {
        $affiliate = $this->identity->requireMember($affiliateId, $this->connectionIdentity());

        return $this->isEnabled() && $affiliate->commercial
            ? $this->descendantPaths($affiliateId)->pluck('descendant_id')->all()
            : [];
    }

    public function descendantCount(string $affiliateId): int
    {
        $affiliate = $this->identity->requireMember($affiliateId, $this->connectionIdentity());

        return $this->isEnabled() && $affiliate->commercial ? $this->descendantPaths($affiliateId)->count() : 0;
    }

    public function teamSales(string $affiliateId, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, ?string $currency = null): int
    {
        $affiliate = $this->identity->requireMember($affiliateId, $this->connectionIdentity());

        if (! $this->isEnabled() || ! $affiliate->commercial) {
            return 0;
        }

        $descendantIds = $this->descendantPaths($affiliateId)->pluck('descendant_id')->all();
        $reference = $affiliate->currency !== null && mb_trim($affiliate->currency) !== ''
            ? mb_strtoupper(mb_trim($affiliate->currency))
            : RevenueVolume::default();
        $revenue = [];

        foreach ($this->conversions->totalsForAffiliates($descendantIds, $reference, $this->connectionIdentity(), $from, $to) as $row) {
            $revenue[$row->currency] = $row->revenueMinor;
        }

        return RevenueVolume::measurableIn($revenue, $currency ?? $reference, $to);
    }

    public function activeDownlineCount(string $affiliateId): int
    {
        $affiliate = $this->identity->requireMember($affiliateId, $this->connectionIdentity());

        return $this->isEnabled() && $affiliate->commercial ? $this->descendantPaths($affiliateId, true)->count() : 0;
    }

    /** @return array<string, mixed> */
    public function tree(string $affiliateId, int $maxDepth = 5): array
    {
        $root = $this->identity->requireMember($affiliateId, $this->connectionIdentity());
        $configuredMaxDepth = (int) config('affiliates.upline.max_depth', 0);
        $maxDepth = max(0, $configuredMaxDepth > 0 ? min($maxDepth, $configuredMaxDepth) : $maxDepth);
        $members = [];

        if ($this->isEnabled() && $root->commercial && $maxDepth > 0) {
            $ids = $this->descendantPaths($affiliateId)->where('depth', '<=', $maxDepth)->pluck('descendant_id')->all();
            $members = $this->identity->members($ids, $this->connectionIdentity());
        }

        $memberIds = array_keys($members);
        $rankIds = array_values(array_unique(array_filter(array_map(
            static fn (AffiliateNetworkMember $member): ?string => $member->rankId,
            [$root, ...array_values($members)],
        ))));
        $rankNames = AffiliateRank::query()->forOwner()->whereIn('id', $rankIds)->pluck('name', 'id');
        $edges = AffiliateUpline::query()->forOwner()->where('depth', 1)
            ->whereIn('ancestor_id', [$affiliateId, ...$memberIds])
            ->whereIn('descendant_id', $memberIds)->orderBy('descendant_id')->get()
            ->groupBy('ancestor_id');

        $build = function (AffiliateNetworkMember $member, int $depth, array $visited) use (&$build, $maxDepth, $edges, $members, $rankNames): array {
            $id = $member->reference->id;
            $visited[$id] = true;
            $children = [];

            if ($depth < $maxDepth) {
                foreach ($edges->get($id, []) as $edge) {
                    $child = $members[$edge->descendant_id] ?? null;

                    if ($child !== null && $child->commercial && ! isset($visited[$edge->descendant_id])) {
                        $children[] = $build($child, $depth + 1, $visited);
                    }
                }
            }

            return [
                'id' => $id,
                'name' => $member->name,
                'code' => $member->code,
                'rank' => $member->rankId === null ? null : $rankNames->get($member->rankId),
                'status' => $member->status,
                'stats' => [
                    'direct_recruits' => $member->directDownlineCount,
                    'total_downline' => $member->totalDownlineCount,
                ],
                'children' => $children,
            ];
        };

        return $build($root, 0, []);
    }

    /** @return Builder<AffiliateUpline> */
    private function descendantPaths(string $id, bool $active = false): Builder
    {
        return $this->paths->commercialEndpoint(
            AffiliateUpline::query()->forOwner()->where('ancestor_id', $id)->where('depth', '>', 0),
            'descendant_id',
            $active,
        );
    }

    private function connectionIdentity(): int
    {
        return spl_object_id((new AffiliateUpline)->getConnection());
    }

    private function isEnabled(): bool
    {
        return (bool) config('affiliates.upline.enabled', false);
    }
}
