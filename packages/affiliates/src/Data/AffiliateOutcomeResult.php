<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

use AIArmada\Affiliates\Enums\AffiliateOutcomeDisposition;

final readonly class AffiliateOutcomeResult
{
    public function __construct(
        public AffiliateOutcomeDisposition $disposition,
        public ?AffiliateConversionData $conversion,
    ) {}

    public function isCreated(): bool
    {
        return $this->disposition === AffiliateOutcomeDisposition::Created;
    }

    public function isReplayed(): bool
    {
        return $this->disposition === AffiliateOutcomeDisposition::Replayed;
    }

    public function isRejected(): bool
    {
        return $this->disposition === AffiliateOutcomeDisposition::Rejected;
    }

    public function isNoAffiliate(): bool
    {
        return $this->disposition === AffiliateOutcomeDisposition::NoAffiliate;
    }
}
