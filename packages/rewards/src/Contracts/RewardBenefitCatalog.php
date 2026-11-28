<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Contracts;

use AIArmada\Rewards\Data\RewardAwardSnapshot;
use AIArmada\Rewards\Data\RewardFinancialReadinessAssessment;
use AIArmada\Rewards\Data\RewardVersionSnapshot;
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
    public function coversProgram(string $programId, ?RewardVersionSnapshot $version): bool;

    public function assess(RewardAwardSnapshot $award, bool $fulfill): RewardFinancialReadinessAssessment;

    public function fulfill(RewardAwardSnapshot $award, CarbonImmutable $decided): void;
}
