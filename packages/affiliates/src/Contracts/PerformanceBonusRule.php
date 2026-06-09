<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use Carbon\CarbonImmutable;

interface PerformanceBonusRule
{
    public function bonusType(): string;

    public function isEnabled(): bool;

    public function calculate(CarbonImmutable $from, CarbonImmutable $to, bool $includeGlobal = false): array;
}
