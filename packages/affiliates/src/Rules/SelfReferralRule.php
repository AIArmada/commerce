<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Rules;

use AIArmada\Affiliates\Contracts\FraudRule;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

final class SelfReferralRule implements FraudRule
{
    public function ruleCode(): string
    {
        return 'SELF_REFERRAL';
    }

    public function analyzeClick(Affiliate $affiliate, Request $request, array $context): ?AffiliateFraudSignal
    {
        return null;
    }

    public function analyzeConversion(AffiliateConversion $conversion, array $context): ?AffiliateFraudSignal
    {
        if (! config('affiliates.tracking.block_self_referral', false)) {
            return null;
        }

        $affiliate = $conversion->affiliate;

        if (! $affiliate?->owner_id || ! $conversion->actor_user_id) {
            return null;
        }

        if (! $this->ownerIsUser($affiliate->owner_type, (string) $affiliate->owner_id, (string) $conversion->actor_user_id)) {
            return null;
        }

        return AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->id,
            'conversion_id' => $conversion->id,
            'rule_code' => $this->ruleCode(),
            'risk_points' => 100,
            'severity' => FraudSeverity::Critical,
            'description' => 'Self-referral detected',
            'evidence' => [
                'affiliate_owner_id' => $affiliate->owner_id,
                'actor_user_id' => $conversion->actor_user_id,
            ],
            'status' => FraudSignalStatus::Detected,
            'detected_at' => CarbonImmutable::now(),
        ]);
    }

    private function ownerIsUser(?string $ownerType, string $ownerId, string $actorId): bool
    {
        $userModel = config('auth.providers.users.model');

        if (! is_string($userModel) || $userModel === '' || ! class_exists($userModel)) {
            return false;
        }

        $userMorphClass = (new $userModel)->getMorphClass();

        return ($ownerType === $userMorphClass || $ownerType === $userModel) && $ownerId === $actorId;
    }
}
