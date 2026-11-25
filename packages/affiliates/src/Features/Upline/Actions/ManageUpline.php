<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Actions;

use AIArmada\Affiliates\Features\Upline\Models\AffiliateUpline;
use AIArmada\Affiliates\Models\Affiliate;
use InvalidArgumentException;

final class ManageUpline
{
    public function add(string $affiliateId, ?string $sponsorId): void
    {
        $this->mutate($affiliateId, $sponsorId, false);
    }

    public function move(string $affiliateId, string $sponsorId): void
    {
        $this->mutate($affiliateId, $sponsorId, true);
    }

    private function mutate(string $affiliateId, ?string $sponsorId, bool $move): void
    {
        $connection = (new Affiliate)->getConnection();
        $connection->transaction(function () use ($affiliateId, $sponsorId, $move): void {
            $ids = $sponsorId === null ? [$affiliateId] : [$affiliateId, $sponsorId];
            $locked = Affiliate::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $affiliate = $locked->get($affiliateId);
            $sponsor = $sponsorId === null ? null : $locked->get($sponsorId);
            if (! $affiliate instanceof Affiliate || ($sponsorId !== null && ! $sponsor instanceof Affiliate)) {
                throw new InvalidArgumentException('Affiliate or sponsor is not accessible.');
            }
            if (! $affiliate->isCommercialPurpose() || ($sponsor !== null && ! $sponsor->isCommercialPurpose())) {
                throw new InvalidArgumentException('Only commercial affiliates can participate in an upline.');
            }
            if (! config('affiliates.upline.enabled', false)) {
                return;
            }
            if ($sponsorId === $affiliateId || ($sponsorId !== null && AffiliateUpline::query()->where('ancestor_id', $affiliateId)->where('descendant_id', $sponsorId)->exists())) {
                throw new InvalidArgumentException('A sponsor cannot be the affiliate or its descendant.');
            }
            if ($sponsor !== null && config('affiliates.owner.enabled', false)
                && ($affiliate->owner_type !== $sponsor->owner_type || (string) $affiliate->owner_id !== (string) $sponsor->owner_id)) {
                throw new InvalidArgumentException('Affiliate and sponsor must belong to the same owner.');
            }

            $oldAncestors = AffiliateUpline::query()->where('descendant_id', $affiliateId)->where('depth', '>', 0)->pluck('ancestor_id')->all();
            $oldSponsor = AffiliateUpline::query()->where('descendant_id', $affiliateId)->where('depth', 1)->value('ancestor_id');
            if (! $move && $oldSponsor !== null && (string) $oldSponsor !== $sponsorId) {
                throw new InvalidArgumentException('Use move to change an existing sponsor.');
            }

            $this->storePath($affiliate, $affiliateId, 0);
            if ($sponsor !== null) {
                $this->storePath($sponsor, (string) $sponsor->getKey(), 0);
            }

            $subtree = AffiliateUpline::query()->where('ancestor_id', $affiliateId)->pluck('depth', 'descendant_id')->all();
            $subtree[$affiliateId] = 0;
            $subtreeIds = array_keys($subtree);
            $ancestors = $sponsorId === null ? [] : AffiliateUpline::query()->where('descendant_id', $sponsorId)
                ->whereHas('ancestor', static fn ($query) => $query->whereCommercialPurpose())->pluck('depth', 'ancestor_id')->all();

            if ($move) {
                AffiliateUpline::query()->whereIn('descendant_id', $subtreeIds)->whereNotIn('ancestor_id', $subtreeIds)->delete();
            }

            $members = Affiliate::query()->whereIn('id', $subtreeIds)->get()->keyBy('id');
            foreach ($subtree as $descendantId => $depth) {
                $member = $members->get($descendantId);
                if (! $member instanceof Affiliate) {
                    throw new InvalidArgumentException('An upline member is not accessible.');
                }
                foreach ($ancestors as $ancestorId => $ancestorDepth) {
                    $this->storePath($member, (string) $ancestorId, (int) $ancestorDepth + 1 + (int) $depth);
                }
            }

            $affiliate->update(['parent_affiliate_id' => $sponsorId]);
            $this->refreshCounts(array_values(array_unique(array_merge($oldAncestors, array_keys($ancestors), $subtreeIds))));
        }, attempts: 3);
    }

    public function remove(string $affiliateId): void
    {
        (new Affiliate)->getConnection()->transaction(function () use ($affiliateId): void {
            Affiliate::query()->whereKey($affiliateId)->lockForUpdate()->firstOrFail();
            if (! config('affiliates.upline.enabled', false)) {
                return;
            }
            $descendants = AffiliateUpline::query()->where('ancestor_id', $affiliateId)->pluck('descendant_id')->all();
            $ancestors = AffiliateUpline::query()->where('descendant_id', $affiliateId)->where('depth', '>', 0)->pluck('ancestor_id')->all();
            if ($descendants === []) {
                return;
            }
            AffiliateUpline::query()->whereIn('descendant_id', $descendants)->delete();
            Affiliate::query()->whereIn('id', $descendants)->update(['parent_affiliate_id' => null, 'direct_downline_count' => 0, 'total_downline_count' => 0, 'network_depth' => 0]);
            $this->refreshCounts($ancestors);
        }, attempts: 3);
    }

    private function storePath(Affiliate $descendant, string $ancestorId, int $depth): void
    {
        $path = AffiliateUpline::query()->firstOrNew(['ancestor_id' => $ancestorId, 'descendant_id' => $descendant->getKey()]);
        $path->forceFill(['depth' => $depth, 'owner_type' => $descendant->owner_type, 'owner_id' => $descendant->owner_id])->save();
    }

    /** @param list<string> $ids */
    private function refreshCounts(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $members = Affiliate::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        $counts = AffiliateUpline::query()->whereIn('ancestor_id', $ids)->where('depth', '>', 0)
            ->whereHas('descendant', static fn ($query) => $query->whereCommercialPurpose())
            ->toBase()->selectRaw('ancestor_id, COUNT(*) AS total, SUM(CASE WHEN depth = 1 THEN 1 ELSE 0 END) AS direct')
            ->groupBy('ancestor_id')->get()->keyBy('ancestor_id');
        $depths = AffiliateUpline::query()->whereIn('descendant_id', $ids)
            ->whereHas('ancestor', static fn ($query) => $query->whereCommercialPurpose())
            ->toBase()->selectRaw('descendant_id, MAX(depth) AS maximum')->groupBy('descendant_id')->pluck('maximum', 'descendant_id');

        foreach ($members as $member) {
            $count = $counts->get($member->getKey());
            $member->update([
                'direct_downline_count' => (int) ($count->direct ?? 0),
                'total_downline_count' => (int) ($count->total ?? 0),
                'network_depth' => (int) ($depths->get($member->getKey()) ?? 0),
            ]);
        }
    }
}
