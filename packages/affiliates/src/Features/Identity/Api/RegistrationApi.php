<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

use AIArmada\CommerceSupport\Data\RecordReference;

interface RegistrationApi
{
    /** @param array<string, mixed> $data */
    public function register(array $data, RecordReference $owner, ?string $referralCode, ?string $cookie, ?RecordReference $actor = null): AffiliateRecord;
}
