<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Features\Fraud\Api\FraudReviewApi;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSeverity;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalStatus;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Features\Identity\Actions\AdministerAffiliate;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycle;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Paused;
use AIArmada\Affiliates\States\Pending;

function lifecycleParityAffiliate(): Affiliate
{
    return Affiliate::create([
        'code' => 'LIFE-' . uniqid(),
        'name' => 'Lifecycle Parity Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
}

test('pausing and disabling clear the opposite lifecycle timestamp', function (): void {
    $affiliate = lifecycleParityAffiliate();
    $lifecycle = app(AffiliateLifecycle::class);

    $lifecycle->pause($affiliate->id);
    $affiliate->refresh();

    expect($affiliate->status)->toBeInstanceOf(Paused::class)
        ->and($affiliate->paused_at)->not->toBeNull()
        ->and($affiliate->deactivated_at)->toBeNull();

    $lifecycle->disable($affiliate->id);
    $affiliate->refresh();

    expect($affiliate->status)->toBeInstanceOf(Disabled::class)
        ->and($affiliate->deactivated_at)->not->toBeNull()
        ->and($affiliate->paused_at)->toBeNull();
});

test('administrative transitions clear timestamps that no longer describe the state', function (): void {
    $affiliate = lifecycleParityAffiliate();
    $admin = app(AdministerAffiliate::class);

    $admin->update($affiliate->id, ['status' => Pending::class]);
    $affiliate->refresh();

    expect($affiliate->activated_at)->toBeNull()
        ->and($affiliate->paused_at)->toBeNull()
        ->and($affiliate->deactivated_at)->toBeNull();

    $admin->update($affiliate->id, ['status' => Active::class]);
    $affiliate->refresh();

    expect($affiliate->status)->toBeInstanceOf(Active::class)
        ->and($affiliate->activated_at)->not->toBeNull()
        ->and($affiliate->paused_at)->toBeNull()
        ->and($affiliate->deactivated_at)->toBeNull();
});

test('fraud review keeps dismissed and confirmed timestamps mutually exclusive', function (): void {
    $affiliate = lifecycleParityAffiliate();
    $signal = AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->id,
        'rule_code' => 'manual-review',
        'risk_points' => 10,
        'severity' => FraudSeverity::Low,
        'description' => 'Manual review signal',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    $review = app(FraudReviewApi::class);
    $review->execute((string) $signal->getKey(), FraudSignalStatus::Dismissed, 'operator');
    $signal->refresh();

    expect($signal->dismissed_at)->not->toBeNull()
        ->and($signal->confirmed_at)->toBeNull();

    $review->execute((string) $signal->getKey(), FraudSignalStatus::Confirmed, 'operator');
    $signal->refresh();

    expect($signal->confirmed_at)->not->toBeNull()
        ->and($signal->dismissed_at)->toBeNull();
});
