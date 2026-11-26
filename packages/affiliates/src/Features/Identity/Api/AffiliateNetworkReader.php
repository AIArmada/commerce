<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateNetworkReader
{
    public function requireMember(string $id, int $connectionIdentity): AffiliateNetworkMember;

    /**
     * @param  list<string>  $ids
     * @return array<string, AffiliateNetworkMember>
     */
    public function members(array $ids, int $connectionIdentity): array;
}
