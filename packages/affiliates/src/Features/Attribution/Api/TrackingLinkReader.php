<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

use DateTimeImmutable;

interface TrackingLinkReader
{
    public function hasRecords(string $affiliateId): bool;

    public function belongsTo(string $linkId, string $affiliateId): bool;

    public function latestForDestination(string $affiliateId, string $destination): ?string;

    public function programVolume(string $affiliateId, string $programId, string $fallbackCurrency, ?DateTimeImmutable $since = null, bool $approvedOnly = false): ProgramLinkVolume;
}
