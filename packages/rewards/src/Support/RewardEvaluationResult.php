<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use AIArmada\Rewards\Enums\RewardEvaluationOutcome;
use AIArmada\Rewards\Models\RewardAward;

/**
 * Deterministic entitlement-evaluation result.
 *
 * Reasons are stable snake_case codes mirroring the preview vocabulary plus
 * live-only codes (unknown_source, fraud_confirmed, source_withdrawn,
 * cap_exceeded, stacking_conflict, duplicate_evaluation, ...). A duplicate
 * evaluation returns the frozen first award instead of minting a second one.
 */
final readonly class RewardEvaluationResult
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public RewardEvaluationOutcome $outcome,
        public RewardAward $award,
        public array $reasons,
        public bool $duplicate = false,
    ) {}
}
