<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

use AIArmada\Affiliates\Features\Attribution\Api\ProgramLinkVolume;
use DateTimeImmutable;

interface LinkConversionReader
{
    public function hasLinkHistory(string $linkId, int $connectionIdentity): bool;

    public function programVolume(string $affiliateId, string $programId, string $fallbackCurrency, int $connectionIdentity, ?DateTimeImmutable $since = null, bool $approvedOnly = false): ProgramLinkVolume;
}
