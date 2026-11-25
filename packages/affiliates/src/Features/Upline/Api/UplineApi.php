<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Api;

use Carbon\CarbonImmutable;

interface UplineApi
{
    public function add(string $affiliateId, ?string $sponsorId = null): void;

    public function remove(string $affiliateId): void;

    public function detachForDeletion(string $affiliateId): void;

    public function move(string $affiliateId, ?string $sponsorId): void;

    /** @return list<UplineMember> */
    public function ancestors(string $affiliateId): array;

    /** @return list<UplineMember> */
    public function descendants(string $affiliateId, ?int $depth = null): array;

    /** @return list<string> */
    public function descendantIds(string $affiliateId): array;

    public function descendantCount(string $affiliateId): int;

    public function teamSales(string $affiliateId, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, ?string $currency = null): int;

    public function activeDownlineCount(string $affiliateId): int;

    /** @return array<string, mixed> */
    public function tree(string $affiliateId, int $maxDepth = 5): array;
}
