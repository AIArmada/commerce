<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Actions;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateRankMaintenance;
use AIArmada\Affiliates\Features\Upline\Api\RankAdministration;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateRank;
use Illuminate\Support\Arr;
use LogicException;

final readonly class AdministerRank implements RankAdministration
{
    public function __construct(private AffiliateRankMaintenance $affiliates) {}

    public function create(array $data): string
    {
        return (string) AffiliateRank::query()->create($this->attributes($data))->getKey();
    }

    public function update(string $rankId, array $data): void
    {
        (new AffiliateRank)->getConnection()->transaction(function () use ($rankId, $data): void {
            AffiliateRank::query()->forOwner()->lockForUpdate()->findOrFail($rankId)->update($this->attributes($data));
        }, attempts: 3);
    }

    public function delete(string $rankId): bool
    {
        $connection = (new AffiliateRank)->getConnection();

        if ($connection !== (new Affiliate)->getConnection()) {
            throw new LogicException('Rank removal requires a shared database connection.');
        }

        return $connection->transaction(function () use ($rankId): bool {
            $rank = AffiliateRank::query()->forOwner()->lockForUpdate()->findOrFail($rankId);
            $this->affiliates->detachRank($rankId);

            if (! $rank->delete()) {
                throw new LogicException('The affiliate rank could not be deleted.');
            }

            return true;
        }, attempts: 3);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return Arr::only($data, ['name', 'slug', 'level', 'min_personal_sales', 'min_team_sales', 'min_active_downlines', 'currency', 'commission_rate_basis_points', 'override_rates', 'benefits', 'metadata']);
    }
}
