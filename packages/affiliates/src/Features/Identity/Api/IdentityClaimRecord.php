<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

use AIArmada\CommerceSupport\Data\RecordReference;
use Carbon\CarbonImmutable;

final readonly class IdentityClaimRecord
{
    public function __construct(public string $id, public string $affiliateId, public RecordReference $claimant, public string $status, public ?CarbonImmutable $claimedAt) {}
}
