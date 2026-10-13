<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

final readonly class RewardFinancialReadinessAssessment
{
    /**
     * @param  list<string>  $blockers
     * @param  list<string>  $notes
     */
    public function __construct(
        public bool $ready,
        public array $blockers = [],
        public array $notes = [],
    ) {}
}
