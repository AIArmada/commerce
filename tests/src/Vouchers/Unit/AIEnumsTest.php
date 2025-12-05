<?php

declare(strict_types=1);

use AIArmada\Vouchers\AI\Enums\AbandonmentRiskLevel;
use AIArmada\Vouchers\AI\Enums\DiscountStrategy;
use AIArmada\Vouchers\AI\Enums\InterventionType;
use AIArmada\Vouchers\AI\Enums\PredictionConfidence;

describe('PredictionConfidence', function (): void {
    it('has all expected cases', function (): void {
        expect(PredictionConfidence::cases())->toHaveCount(5);
        expect(PredictionConfidence::VeryLow->value)->toBe('very_low');
        expect(PredictionConfidence::Low->value)->toBe('low');
        expect(PredictionConfidence::Medium->value)->toBe('medium');
        expect(PredictionConfidence::High->value)->toBe('high');
        expect(PredictionConfidence::VeryHigh->value)->toBe('very_high');
    });

    it('returns correct min thresholds', function (): void {
        expect(PredictionConfidence::VeryLow->getMinThreshold())->toBe(0.0);
        expect(PredictionConfidence::Low->getMinThreshold())->toBe(0.2);
        expect(PredictionConfidence::Medium->getMinThreshold())->toBe(0.4);
        expect(PredictionConfidence::High->getMinThreshold())->toBe(0.6);
        expect(PredictionConfidence::VeryHigh->getMinThreshold())->toBe(0.8);
    });

    it('returns correct max thresholds', function (): void {
        expect(PredictionConfidence::VeryLow->getMaxThreshold())->toBe(0.2);
        expect(PredictionConfidence::Low->getMaxThreshold())->toBe(0.4);
        expect(PredictionConfidence::Medium->getMaxThreshold())->toBe(0.6);
        expect(PredictionConfidence::High->getMaxThreshold())->toBe(0.8);
        expect(PredictionConfidence::VeryHigh->getMaxThreshold())->toBe(1.0);
    });

    it('returns correct labels', function (): void {
        expect(PredictionConfidence::VeryLow->getLabel())->toBe('Very Low Confidence');
        expect(PredictionConfidence::High->getLabel())->toBe('High Confidence');
    });

    it('returns correct colors', function (): void {
        expect(PredictionConfidence::VeryLow->getColor())->toBe('danger');
        expect(PredictionConfidence::High->getColor())->toBe('success');
    });

    it('correctly identifies trustworthy levels', function (): void {
        expect(PredictionConfidence::VeryLow->isTrustworthy())->toBeFalse();
        expect(PredictionConfidence::Low->isTrustworthy())->toBeFalse();
        expect(PredictionConfidence::Medium->isTrustworthy())->toBeTrue();
        expect(PredictionConfidence::High->isTrustworthy())->toBeTrue();
        expect(PredictionConfidence::VeryHigh->isTrustworthy())->toBeTrue();
    });

    it('creates from score correctly', function (): void {
        expect(PredictionConfidence::fromScore(0.1))->toBe(PredictionConfidence::VeryLow);
        expect(PredictionConfidence::fromScore(0.3))->toBe(PredictionConfidence::Low);
        expect(PredictionConfidence::fromScore(0.5))->toBe(PredictionConfidence::Medium);
        expect(PredictionConfidence::fromScore(0.7))->toBe(PredictionConfidence::High);
        expect(PredictionConfidence::fromScore(0.9))->toBe(PredictionConfidence::VeryHigh);
    });
});

describe('AbandonmentRiskLevel', function (): void {
    it('has all expected cases', function (): void {
        expect(AbandonmentRiskLevel::cases())->toHaveCount(4);
        expect(AbandonmentRiskLevel::Low->value)->toBe('low');
        expect(AbandonmentRiskLevel::Medium->value)->toBe('medium');
        expect(AbandonmentRiskLevel::High->value)->toBe('high');
        expect(AbandonmentRiskLevel::Critical->value)->toBe('critical');
    });

    it('returns correct min scores', function (): void {
        expect(AbandonmentRiskLevel::Low->getMinScore())->toBe(0.0);
        expect(AbandonmentRiskLevel::Medium->getMinScore())->toBe(0.3);
        expect(AbandonmentRiskLevel::High->getMinScore())->toBe(0.6);
        expect(AbandonmentRiskLevel::Critical->getMinScore())->toBe(0.8);
    });

    it('returns correct labels', function (): void {
        expect(AbandonmentRiskLevel::Low->getLabel())->toBe('Low Risk');
        expect(AbandonmentRiskLevel::Critical->getLabel())->toBe('Critical Risk');
    });

    it('returns correct colors', function (): void {
        expect(AbandonmentRiskLevel::Low->getColor())->toBe('success');
        expect(AbandonmentRiskLevel::High->getColor())->toBe('danger');
    });

    it('returns correct recommended interventions', function (): void {
        expect(AbandonmentRiskLevel::Low->getRecommendedIntervention())->toBe('none');
        expect(AbandonmentRiskLevel::Medium->getRecommendedIntervention())->toBe('exit_popup');
        expect(AbandonmentRiskLevel::High->getRecommendedIntervention())->toBe('discount_offer');
        expect(AbandonmentRiskLevel::Critical->getRecommendedIntervention())->toBe('recovery_email');
    });

    it('correctly identifies immediate action requirements', function (): void {
        expect(AbandonmentRiskLevel::Low->requiresImmediateAction())->toBeFalse();
        expect(AbandonmentRiskLevel::Medium->requiresImmediateAction())->toBeFalse();
        expect(AbandonmentRiskLevel::High->requiresImmediateAction())->toBeTrue();
        expect(AbandonmentRiskLevel::Critical->requiresImmediateAction())->toBeTrue();
    });

    it('returns urgency weights', function (): void {
        expect(AbandonmentRiskLevel::Low->getUrgencyWeight())->toBe(1);
        expect(AbandonmentRiskLevel::Critical->getUrgencyWeight())->toBe(8);
    });

    it('creates from score correctly', function (): void {
        expect(AbandonmentRiskLevel::fromScore(0.1))->toBe(AbandonmentRiskLevel::Low);
        expect(AbandonmentRiskLevel::fromScore(0.4))->toBe(AbandonmentRiskLevel::Medium);
        expect(AbandonmentRiskLevel::fromScore(0.7))->toBe(AbandonmentRiskLevel::High);
        expect(AbandonmentRiskLevel::fromScore(0.9))->toBe(AbandonmentRiskLevel::Critical);
    });
});

describe('DiscountStrategy', function (): void {
    it('has all expected cases', function (): void {
        expect(DiscountStrategy::cases())->toHaveCount(5);
        expect(DiscountStrategy::Percentage->value)->toBe('percentage');
        expect(DiscountStrategy::FixedAmount->value)->toBe('fixed_amount');
        expect(DiscountStrategy::FreeShipping->value)->toBe('free_shipping');
        expect(DiscountStrategy::BuyXGetY->value)->toBe('buy_x_get_y');
        expect(DiscountStrategy::Tiered->value)->toBe('tiered');
    });

    it('returns correct labels', function (): void {
        expect(DiscountStrategy::Percentage->getLabel())->toBe('Percentage Discount');
        expect(DiscountStrategy::FreeShipping->getLabel())->toBe('Free Shipping');
    });

    it('returns descriptions', function (): void {
        expect(DiscountStrategy::Percentage->getDescription())->toContain('percentage');
        expect(DiscountStrategy::FreeShipping->getDescription())->toContain('shipping');
    });

    it('identifies high value cart suitability', function (): void {
        expect(DiscountStrategy::FixedAmount->isSuitableForHighValueCart())->toBeTrue();
        expect(DiscountStrategy::FreeShipping->isSuitableForHighValueCart())->toBeTrue();
        expect(DiscountStrategy::Percentage->isSuitableForHighValueCart())->toBeFalse();
    });

    it('identifies low value cart suitability', function (): void {
        expect(DiscountStrategy::Percentage->isSuitableForLowValueCart())->toBeTrue();
        expect(DiscountStrategy::FreeShipping->isSuitableForLowValueCart())->toBeTrue();
        expect(DiscountStrategy::FixedAmount->isSuitableForLowValueCart())->toBeFalse();
    });

    it('returns psychological appeal scores', function (): void {
        expect(DiscountStrategy::FreeShipping->getPsychologicalAppeal())->toBe(5);
        expect(DiscountStrategy::Percentage->getPsychologicalAppeal())->toBe(4);
    });

    it('returns margin protection scores', function (): void {
        expect(DiscountStrategy::BuyXGetY->getMarginProtection())->toBe(5);
        expect(DiscountStrategy::Percentage->getMarginProtection())->toBe(2);
    });
});

describe('InterventionType', function (): void {
    it('has all expected cases', function (): void {
        expect(InterventionType::cases())->toHaveCount(7);
        expect(InterventionType::None->value)->toBe('none');
        expect(InterventionType::ExitPopup->value)->toBe('exit_popup');
        expect(InterventionType::DiscountOffer->value)->toBe('discount_offer');
        expect(InterventionType::RecoveryEmail->value)->toBe('recovery_email');
    });

    it('returns correct labels', function (): void {
        expect(InterventionType::None->getLabel())->toBe('No Intervention');
        expect(InterventionType::ExitPopup->getLabel())->toBe('Exit Intent Popup');
    });

    it('returns typical delay minutes', function (): void {
        expect(InterventionType::None->getTypicalDelayMinutes())->toBe(0);
        expect(InterventionType::ExitPopup->getTypicalDelayMinutes())->toBe(0);
        expect(InterventionType::RecoveryEmail->getTypicalDelayMinutes())->toBe(60);
        expect(InterventionType::Retargeting->getTypicalDelayMinutes())->toBe(1440);
    });

    it('returns effectiveness scores', function (): void {
        expect(InterventionType::DiscountOffer->getEffectivenessScore())->toBe(5);
        expect(InterventionType::None->getEffectivenessScore())->toBe(1);
    });

    it('returns cost scores', function (): void {
        expect(InterventionType::LiveChat->getCostScore())->toBe(5);
        expect(InterventionType::ExitPopup->getCostScore())->toBe(1);
    });

    it('identifies discount requirements', function (): void {
        expect(InterventionType::DiscountOffer->requiresDiscount())->toBeTrue();
        expect(InterventionType::ExitPopup->requiresDiscount())->toBeTrue();
        expect(InterventionType::RecoveryEmail->requiresDiscount())->toBeTrue();
        expect(InterventionType::LiveChat->requiresDiscount())->toBeFalse();
    });

    it('identifies real-time interventions', function (): void {
        expect(InterventionType::ExitPopup->isRealTime())->toBeTrue();
        expect(InterventionType::DiscountOffer->isRealTime())->toBeTrue();
        expect(InterventionType::RecoveryEmail->isRealTime())->toBeFalse();
        expect(InterventionType::PushNotification->isRealTime())->toBeFalse();
    });
});
