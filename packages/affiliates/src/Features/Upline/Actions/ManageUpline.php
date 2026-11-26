<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Actions;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateNetworkMaintenance;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateNetworkMember;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateUpline;
use AIArmada\Affiliates\Features\Upline\Queries\UplinePathQuery;
use InvalidArgumentException;

final readonly class ManageUpline
{
    public function __construct(private AffiliateNetworkMaintenance $identity, private UplinePathQuery $paths) {}

    public function add(string $affiliateId, ?string $sponsorId): void
    {
        $this->mutate($affiliateId, $sponsorId, false);
    }

    public function move(string $affiliateId, ?string $sponsorId): void
    {
        $this->mutate($affiliateId, $sponsorId, true);
    }

    private function mutate(string $affiliateId, ?string $sponsorId, bool $move): void
    {
        $connection = (new AffiliateUpline)->getConnection();
        $connectionIdentity = spl_object_id($connection);

        $connection->transaction(function () use ($affiliateId, $sponsorId, $move, $connectionIdentity): void {
            $ids = $sponsorId === null ? [$affiliateId] : [$affiliateId, $sponsorId];
            $locked = $this->identity->lockMembers($ids, $connectionIdentity);
            $affiliate = $locked[$affiliateId];
            $sponsor = $sponsorId === null ? null : $locked[$sponsorId];

            if (! $affiliate->commercial || ($sponsor !== null && ! $sponsor->commercial)) {
                throw new InvalidArgumentException('Only commercial affiliates can participate in an upline.');
            }
            if (! config('affiliates.upline.enabled', false)) {
                return;
            }
            if ($sponsorId === $affiliateId || ($sponsorId !== null && AffiliateUpline::query()->forOwner()->where('ancestor_id', $affiliateId)->where('descendant_id', $sponsorId)->exists())) {
                throw new InvalidArgumentException('A sponsor cannot be the affiliate or its descendant.');
            }
            if ($sponsor !== null && config('affiliates.owner.enabled', false)
                && ($affiliate->reference->ownerType !== $sponsor->reference->ownerType || $affiliate->reference->ownerId !== $sponsor->reference->ownerId)) {
                throw new InvalidArgumentException('Affiliate and sponsor must belong to the same owner.');
            }

            $oldAncestors = AffiliateUpline::query()->forOwner()->where('descendant_id', $affiliateId)->where('depth', '>', 0)->pluck('ancestor_id')->all();
            $oldSponsor = AffiliateUpline::query()->forOwner()->where('descendant_id', $affiliateId)->where('depth', 1)->value('ancestor_id');
            if (! $move && $oldSponsor !== null && (string) $oldSponsor !== $sponsorId) {
                throw new InvalidArgumentException('Use move to change an existing sponsor.');
            }

            $this->storePath($affiliate, $affiliateId, 0);
            if ($sponsor !== null) {
                $this->storePath($sponsor, $sponsor->reference->id, 0);
            }

            $subtree = AffiliateUpline::query()->forOwner()->where('ancestor_id', $affiliateId)->pluck('depth', 'descendant_id')->all();
            $subtree[$affiliateId] = 0;
            $subtreeIds = array_keys($subtree);
            $ancestors = $sponsorId === null ? [] : $this->paths->commercialEndpoint(AffiliateUpline::query()->forOwner()->where('descendant_id', $sponsorId), 'ancestor_id')->pluck('depth', 'ancestor_id')->all();

            if ($move) {
                AffiliateUpline::query()->forOwner()->whereIn('descendant_id', $subtreeIds)->whereNotIn('ancestor_id', $subtreeIds)->delete();
            }

            $members = $this->identity->lockMembers($subtreeIds, $connectionIdentity);
            foreach ($subtree as $descendantId => $depth) {
                $member = $members[$descendantId];

                if (! $member->commercial || (config('affiliates.owner.enabled', false)
                    && ($member->reference->ownerType !== $affiliate->reference->ownerType || $member->reference->ownerId !== $affiliate->reference->ownerId))) {
                    throw new InvalidArgumentException('An upline member is not accessible.');
                }
                foreach ($ancestors as $ancestorId => $ancestorDepth) {
                    $this->storePath($member, (string) $ancestorId, (int) $ancestorDepth + 1 + (int) $depth);
                }
            }

            $this->identity->synchronizeParent($affiliateId, $sponsorId, $connectionIdentity);
            $this->refreshCounts(array_values(array_unique(array_merge($oldAncestors, array_keys($ancestors), $subtreeIds))), $connectionIdentity);
        }, attempts: 3);
    }

    public function remove(string $affiliateId): void
    {
        $connection = (new AffiliateUpline)->getConnection();
        $connectionIdentity = spl_object_id($connection);

        $connection->transaction(function () use ($affiliateId, $connectionIdentity): void {
            $this->identity->lockMembers([$affiliateId], $connectionIdentity);
            if (! config('affiliates.upline.enabled', false)) {
                return;
            }
            $descendants = AffiliateUpline::query()->forOwner()->where('ancestor_id', $affiliateId)->pluck('descendant_id')->all();
            $ancestors = AffiliateUpline::query()->forOwner()->where('descendant_id', $affiliateId)->where('depth', '>', 0)->pluck('ancestor_id')->all();
            if ($descendants === []) {
                return;
            }
            AffiliateUpline::query()->forOwner()->whereIn('descendant_id', $descendants)->delete();
            $this->identity->clearNetwork($descendants, $connectionIdentity);
            $this->refreshCounts($ancestors, $connectionIdentity);
        }, attempts: 3);
    }

    public function detachForDeletion(string $affiliateId): void
    {
        $connection = (new AffiliateUpline)->getConnection();
        $connectionIdentity = spl_object_id($connection);

        $connection->transaction(function () use ($affiliateId, $connectionIdentity): void {
            $this->identity->lockMembers([$affiliateId], $connectionIdentity);

            $descendants = AffiliateUpline::query()->forOwner()->where('ancestor_id', $affiliateId)
                ->where('depth', '>', 0)->pluck('descendant_id')->all();
            $ancestors = AffiliateUpline::query()->forOwner()->where('descendant_id', $affiliateId)
                ->where('depth', '>', 0)->pluck('ancestor_id')->all();

            if ($descendants !== []) {
                AffiliateUpline::query()->forOwner()->whereIn('descendant_id', $descendants)
                    ->whereNotIn('ancestor_id', $descendants)->delete();
            }

            AffiliateUpline::query()->forOwner()->where(function ($query) use ($affiliateId): void {
                $query->where('ancestor_id', $affiliateId)->orWhere('descendant_id', $affiliateId);
            })->delete();

            $this->refreshCounts(array_values(array_unique(array_merge($ancestors, $descendants))), $connectionIdentity);
        }, attempts: 3);
    }

    private function storePath(AffiliateNetworkMember $descendant, string $ancestorId, int $depth): void
    {
        $path = AffiliateUpline::query()->forOwner()->firstOrNew(['ancestor_id' => $ancestorId, 'descendant_id' => $descendant->reference->id]);
        $path->forceFill(['depth' => $depth, 'owner_type' => $descendant->reference->ownerType, 'owner_id' => $descendant->reference->ownerId])->save();
    }

    /** @param list<string> $ids */
    private function refreshCounts(array $ids, int $connectionIdentity): void
    {
        if ($ids === []) {
            return;
        }

        $members = $this->identity->lockMembers($ids, $connectionIdentity);
        $counts = $this->paths->commercialEndpoint(AffiliateUpline::query()->forOwner()->whereIn('ancestor_id', $ids)->where('depth', '>', 0), 'descendant_id')
            ->toBase()->selectRaw('ancestor_id, COUNT(*) AS total, SUM(CASE WHEN depth = 1 THEN 1 ELSE 0 END) AS direct')
            ->groupBy('ancestor_id')->get()->keyBy('ancestor_id');
        $depths = $this->paths->commercialEndpoint(AffiliateUpline::query()->forOwner()->whereIn('descendant_id', $ids), 'ancestor_id')
            ->toBase()->selectRaw('descendant_id, MAX(depth) AS maximum')->groupBy('descendant_id')->pluck('maximum', 'descendant_id');

        $statistics = [];

        foreach ($members as $id => $member) {
            $count = $counts->get($id);
            $statistics[$id] = [
                'direct' => (int) ($count->direct ?? 0),
                'total' => (int) ($count->total ?? 0),
                'depth' => (int) ($depths->get($id) ?? 0),
            ];
        }

        $this->identity->synchronizeCounts($statistics, $connectionIdentity);
    }
}
