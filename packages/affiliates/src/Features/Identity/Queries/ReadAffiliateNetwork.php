<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateNetworkMember;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateNetworkReader;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

final readonly class ReadAffiliateNetwork implements AffiliateNetworkReader
{
    public function __construct(private GuardAffiliateAccess $access) {}

    public function requireMember(string $id, int $connectionIdentity): AffiliateNetworkMember
    {
        return $this->members([$id], $connectionIdentity)[$id]
            ?? throw (new ModelNotFoundException)->setModel(Affiliate::class, [$id]);
    }

    public function members(array $ids, int $connectionIdentity): array
    {
        $this->access->assertConnection($connectionIdentity);
        $members = [];
        $ids = array_values(array_unique(array_filter($ids, static fn (string $id): bool => Str::isUuid($id))));

        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (Affiliate::query()->forOwner()->whereKey($chunk)->get() as $affiliate) {
                $members[$affiliate->id] = self::project($affiliate);
            }
        }

        return $members;
    }

    public static function project(Affiliate $affiliate): AffiliateNetworkMember
    {
        return new AffiliateNetworkMember(
            reference: $affiliate->lifecycleReference(),
            commercial: $affiliate->isCommercialPurpose(),
            name: $affiliate->name,
            code: $affiliate->code,
            currency: $affiliate->currency,
            status: $affiliate->status->getValue(),
            rankId: $affiliate->rank_id,
            directDownlineCount: $affiliate->direct_downline_count,
            totalDownlineCount: $affiliate->total_downline_count,
        );
    }
}
