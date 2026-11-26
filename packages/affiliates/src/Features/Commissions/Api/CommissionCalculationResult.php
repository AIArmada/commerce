<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

final readonly class CommissionCalculationResult
{
    public function __construct(
        public int $baseCommissionMinor,
        public int $volumeBonusMinor,
        public int $promotionBonusMinor,
        public int $finalCommissionMinor,
        public array $appliedRules = [],
        public array $metadata = []
    ) {}

    public function getTotalBonusMinor(): int
    {
        return $this->volumeBonusMinor + $this->promotionBonusMinor;
    }

    public function hasBonus(): bool
    {
        return $this->getTotalBonusMinor() > 0;
    }

    public function toArray(): array
    {
        return [
            'base_commission_minor' => $this->baseCommissionMinor,
            'volume_bonus_minor' => $this->volumeBonusMinor,
            'promotion_bonus_minor' => $this->promotionBonusMinor,
            'final_commission_minor' => $this->finalCommissionMinor,
            'applied_rules' => $this->appliedRules,
            'metadata' => $this->metadata,
        ];
    }
}
