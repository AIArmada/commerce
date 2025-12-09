<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateRank;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AffiliateRankChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Affiliate $affiliate,
        public readonly ?AffiliateRank $fromRank,
        public readonly ?AffiliateRank $toRank,
        public readonly RankQualificationReason $reason
    ) {}

    public function isPromotion(): bool
    {
        if ($this->fromRank === null) {
            return $this->toRank !== null;
        }

        if ($this->toRank === null) {
            return false;
        }

        return $this->toRank->isHigherThan($this->fromRank);
    }

    public function isDemotion(): bool
    {
        if ($this->fromRank === null) {
            return false;
        }

        if ($this->toRank === null) {
            return true;
        }

        return $this->toRank->isLowerThan($this->fromRank);
    }
}
