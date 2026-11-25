<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Actions;

use AIArmada\Affiliates\Features\Upline\Api\UplineApi;
use AIArmada\Affiliates\Features\Upline\Api\UplineMember;
use AIArmada\Affiliates\Features\Upline\Queries\UplineQuery;
use Carbon\CarbonImmutable;

final readonly class UplineGateway implements UplineApi
{
    public function __construct(private ManageUpline $write, private UplineQuery $read) {}

    public function add(string $affiliateId, ?string $sponsorId = null): void
    {
        $this->write->add($affiliateId, $sponsorId);
    }

    public function remove(string $affiliateId): void
    {
        $this->write->remove($affiliateId);
    }

    public function move(string $affiliateId, string $sponsorId): void
    {
        $this->write->move($affiliateId, $sponsorId);
    }

    /** @return list<UplineMember> */
    public function ancestors(string $affiliateId): array
    {
        return $this->read->ancestors($affiliateId);
    }

    /** @return list<UplineMember> */
    public function descendants(string $affiliateId, ?int $depth = null): array
    {
        return $this->read->descendants($affiliateId, $depth);
    }

    /** @return list<string> */
    public function descendantIds(string $affiliateId): array
    {
        return $this->read->descendantIds($affiliateId);
    }

    public function descendantCount(string $affiliateId): int
    {
        return $this->read->descendantCount($affiliateId);
    }

    public function teamSales(string $affiliateId, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, ?string $currency = null): int
    {
        return $this->read->teamSales($affiliateId, $from, $to, $currency);
    }

    public function activeDownlineCount(string $affiliateId): int
    {
        return $this->read->activeDownlineCount($affiliateId);
    }

    /** @return array<string, mixed> */
    public function tree(string $affiliateId, int $maxDepth = 5): array
    {
        return $this->read->tree($affiliateId, $maxDepth);
    }
}
