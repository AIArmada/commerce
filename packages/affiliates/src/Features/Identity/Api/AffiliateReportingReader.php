<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

use Carbon\CarbonImmutable;

interface AffiliateReportingReader
{
    public function available(): bool;

    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    public function names(array $ids): array;

    /** @return iterable<string> */
    public function scopedIds(int $connectionIdentity, bool $commercialOnly = false): iterable;

    /** @return array<string, list<AffiliateCohortMember>> */
    public function cohorts(CarbonImmutable $from, CarbonImmutable $to): array;

    /** @param callable(): int $callback */
    public function runForOwners(callable $callback): int;

    /**
     * @template T of array<string, int>
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    public function summarizeForOwners(callable $callback): ?array;

    public function nameFor(string $affiliateId, ?string $ownerType, ?string $ownerId, int $connectionIdentity): ?string;

    /** @return array<string, string> */
    public function codeOptions(): array;
}
