<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

use AIArmada\Affiliates\Data\AffiliateData;
use AIArmada\Affiliates\Data\AwardRecipient;

final readonly class AffiliateProfile
{
    public function __construct(
        public AffiliateLifecycleReference $reference,
        public string $code,
        public string $name,
        public string $handle,
        public ?string $currency,
        public bool $active,
        public bool $commercial,
        public bool $canBeAttributed,
        public bool $canReceivePayout,
        public AwardRecipient $awardRecipient,
        public ?string $parentAffiliateId,
        public string $status,
        public AffiliateData $data,
        public ?string $rankId,
    ) {}
}
