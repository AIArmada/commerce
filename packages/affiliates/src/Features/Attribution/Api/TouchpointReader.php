<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

use AIArmada\Affiliates\Data\AttributionTouch;
use Carbon\CarbonImmutable;
use DateTimeInterface;

interface TouchpointReader
{
    /** @return list<AttributionTouch> */
    public function distributionTouches(string $attributionId): array;

    public function fingerprintCount(string $affiliateId, string $fingerprint, CarbonImmutable $since): int;

    /** @return array{ip_address: string, touched_at: CarbonImmutable}|null */
    public function latestOtherIp(string $affiliateId, string $ipAddress): ?array;

    /** @return array{clicks: int, unique_clicks: int} */
    public function clickStats(string $affiliateId, DateTimeInterface $start, DateTimeInterface $end): array;

    /** @return array{by_source: array<string, int>, by_campaign: array<string, int>} */
    public function breakdown(string $affiliateId, DateTimeInterface $start, DateTimeInterface $end): array;

    /** @return list<array{subject_type: string, subject_key: string, subject_title_snapshot: string|null, visits: int}> */
    public function subjectVisits(DateTimeInterface $start, DateTimeInterface $end): array;

    public function existsForAffiliate(string $touchpointId, string $affiliateId): bool;
}
