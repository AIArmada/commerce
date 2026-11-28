<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Contracts;

use AIArmada\Rewards\Data\RewardReadinessAssessment;
use AIArmada\Rewards\Data\RewardVersionSnapshot;

/**
 * Readiness checks for the program lifecycle.
 *
 * Readiness (draft -> ready) is structural: a published version with valid
 * rules/terms exists. Activation (ready -> active) additionally requires the
 * fulfillment dependencies of the configured components. The null
 * implementation truthfully rejects money/benefit activation because no
 * funding, payee, retention, or reversal adapters exist in this slice; real
 * adapters arrive with the later finance scope. It never reports funded or
 * payee readiness.
 *
 * Caller assumption: invoked server-side by staff-driven program actions.
 * No member-reachable approval/activation endpoint exists in this slice.
 */
interface RewardReadinessChecker
{
    public function canMarkReady(string $programId, ?RewardVersionSnapshot $version): RewardReadinessAssessment;

    public function canActivate(string $programId, ?RewardVersionSnapshot $version): RewardReadinessAssessment;
}
