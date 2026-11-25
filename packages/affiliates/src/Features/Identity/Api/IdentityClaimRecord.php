<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

use Carbon\CarbonImmutable;
use AIArmada\CommerceSupport\Data\RecordReference;

final readonly class IdentityClaimRecord
{
    public function __construct(public string $id, public string $affiliateId, public RecordReference $claimant, public string $status, public ?CarbonImmutable $claimedAt) {}
}
