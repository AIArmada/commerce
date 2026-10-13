<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Contracts;

use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardProgram;
use AIArmada\Rewards\Models\RewardProgramVersion;
use AIArmada\Rewards\Support\RewardFinancialReadinessAssessment;
use Carbon\CarbonImmutable;

/**
 * Benefit catalog + fulfillment boundary.
 *
 * Covers program readiness (catalog availability for configured benefit
 * keys), per-award financial readiness, and the fulfillment step invoked
 * inside the award transition (direct-entitlement grant issuance). Bound
 * by the app when the benefit scope is present; financial readiness and
 * fulfillment refuse explicitly until then.
 */
interface RewardBenefitCatalog
{
    public function coversProgram(RewardProgram $program, ?RewardProgramVersion $version): bool;

    public function assess(RewardAward $award, bool $fulfill): RewardFinancialReadinessAssessment;

    public function fulfill(RewardAward $award, CarbonImmutable $decided): void;
}
