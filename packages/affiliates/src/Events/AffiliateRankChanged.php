<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Events;

use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Features\Upline\Api\QualifiedRank;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class AffiliateRankChanged
{
    use Dispatchable;

    public function __construct(
        public string $affiliateId,
        public ?QualifiedRank $fromRank,
        public ?QualifiedRank $toRank,
        public RankQualificationReason $reason
    ) {}

    public function isPromotion(): bool
    {
        if ($this->fromRank === null) {
            return $this->toRank !== null;
        }

        if ($this->toRank === null) {
            return false;
        }

        return $this->toRank->level > $this->fromRank->level;
    }

    public function isDemotion(): bool
    {
        if ($this->fromRank === null) {
            return false;
        }

        if ($this->toRank === null) {
            return true;
        }

        return $this->toRank->level < $this->fromRank->level;
    }
}
