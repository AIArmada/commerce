<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

use DateTimeInterface;

interface AttributionReportingReader
{
    public function available(): bool;

    public function find(string $id): ?AttributionReportingRecord;

    public function latestForVoucher(string $voucherCode): ?AttributionReportingRecord;

    /** @return array<string, string> */
    public function options(string $column): array;

    public function count(?string $affiliateId = null): int;

    public function countFirstSeen(string $affiliateId, DateTimeInterface $start, DateTimeInterface $end): int;

    public function countWindow(DateTimeInterface $start, DateTimeInterface $end): int;

    /** @return list<array{subject_type: string, subject_key: string, subject_title_snapshot: string|null, attributions: int}> */
    public function subjectCounts(DateTimeInterface $start, DateTimeInterface $end): array;

    public function assertSharedConnection(int $connectionIdentity): void;
}
