<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Actions;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateNetworkMaintenance;
use AIArmada\Affiliates\Features\Identity\Queries\GuardAffiliateAccess;
use AIArmada\Affiliates\Features\Identity\Queries\ReadAffiliateNetwork;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final readonly class MaintainAffiliateNetwork implements AffiliateNetworkMaintenance
{
    public function __construct(private GuardAffiliateAccess $access) {}

    public function lockMembers(array $ids, int $connectionIdentity): array
    {
        $members = [];

        foreach ($this->lockedModels($ids, $connectionIdentity) as $affiliate) {
            $members[$affiliate->id] = ReadAffiliateNetwork::project($affiliate);
        }

        return $members;
    }

    public function synchronizeParent(string $affiliateId, ?string $sponsorId, int $connectionIdentity): void
    {
        if ($affiliateId === $sponsorId) {
            throw new InvalidArgumentException('An affiliate cannot be its own sponsor.');
        }

        $members = $this->lockedModels($sponsorId === null ? [$affiliateId] : [$affiliateId, $sponsorId], $connectionIdentity)->keyBy('id');
        $affiliate = $members->get($affiliateId);
        $sponsor = $sponsorId === null ? null : $members->get($sponsorId);

        if (! $affiliate instanceof Affiliate || ($sponsorId !== null && ! $sponsor instanceof Affiliate)) {
            throw new InvalidArgumentException('Affiliate or sponsor is not accessible.');
        }

        if (! $affiliate->isCommercialPurpose() || ($sponsor !== null && ! $sponsor->isCommercialPurpose())) {
            throw new InvalidArgumentException('Only commercial affiliates can participate in an upline.');
        }

        if ($sponsor !== null && config('affiliates.owner.enabled', false)
            && ($affiliate->owner_type !== $sponsor->owner_type || $affiliate->owner_id !== $sponsor->owner_id)) {
            throw new InvalidArgumentException('Affiliate and sponsor must belong to the same owner.');
        }

        // Independently reject cycles in the canonical parent chain even
        // when closure rows are missing or stale.
        $visited = [$affiliateId => true];
        $current = $sponsor;

        while ($current !== null) {
            if (isset($visited[$current->id])) {
                throw new InvalidArgumentException('The sponsor would create a hierarchy cycle.');
            }

            $visited[$current->id] = true;
            $parentId = $current->parent_affiliate_id;

            if ($parentId === null) {
                break;
            }

            $current = $this->lockedModels([$parentId], $connectionIdentity)->first();
        }

        if ($affiliate->parent_affiliate_id !== $sponsorId) {
            $affiliate->update(['parent_affiliate_id' => $sponsorId]);
        }
    }

    public function clearNetwork(array $ids, int $connectionIdentity): void
    {
        $models = $this->lockedModels($ids, $connectionIdentity);

        if ($models->isEmpty()) {
            return;
        }

        Affiliate::query()->forOwner()->whereKey($models->modelKeys())->update([
            'parent_affiliate_id' => null,
            'direct_downline_count' => 0,
            'total_downline_count' => 0,
            'network_depth' => 0,
        ]);
    }

    public function synchronizeCounts(array $counts, int $connectionIdentity): void
    {
        foreach ($this->lockedModels(array_keys($counts), $connectionIdentity) as $affiliate) {
            $count = $counts[$affiliate->id];

            if ($count['direct'] < 0 || $count['total'] < $count['direct'] || $count['depth'] < 0) {
                throw new InvalidArgumentException('Invalid affiliate network counts.');
            }

            $affiliate->fill([
                'direct_downline_count' => $count['direct'],
                'total_downline_count' => $count['total'],
                'network_depth' => $count['depth'],
            ]);

            if ($affiliate->isDirty()) {
                $affiliate->save();
            }
        }
    }

    /**
     * @param list<string> $ids
     * @return Collection<int, Affiliate>
     */
    private function lockedModels(array $ids, int $connectionIdentity): Collection
    {
        $this->access->assertConnection($connectionIdentity);

        if ((new Affiliate)->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Affiliate network maintenance requires an active transaction.');
        }

        $ids = array_values(array_unique($ids));

        foreach ($ids as $id) {
            if (! Str::isUuid($id)) {
                throw new InvalidArgumentException('Affiliate or sponsor is not accessible.');
            }
        }

        if ($ids === []) {
            return new Collection;
        }

        $models = Affiliate::query()->forOwner()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

        if ($models->count() !== count($ids)) {
            throw new InvalidArgumentException('Affiliate or sponsor is not accessible.');
        }

        return $models;
    }
}
