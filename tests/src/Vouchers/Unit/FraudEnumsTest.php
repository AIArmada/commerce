<?php

declare(strict_types=1);

use AIArmada\Vouchers\Fraud\Enums\FraudRiskLevel;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;

describe('FraudSignalType', function (): void {
    it('can get label for all signal types', function (): void {
        expect(FraudSignalType::HighRedemptionVelocity->getLabel())->toBe('High Redemption Velocity')
            ->and(FraudSignalType::MultipleAccountsAttempt->getLabel())->toBe('Multiple Accounts Attempt')
            ->and(FraudSignalType::CodeSharingDetected->getLabel())->toBe('Code Sharing Detected')
            ->and(FraudSignalType::UnusualTimePattern->getLabel())->toBe('Unusual Time Pattern');
    });

    it('can get description for all signal types', function (): void {
        expect(FraudSignalType::HighRedemptionVelocity->getDescription())
            ->toBe('Voucher is being redeemed at an unusually high rate');
        expect(FraudSignalType::GeoAnomalyDetected->getDescription())
            ->toBe('Redemption from unexpected geographic location');
    });

    it('can get category for signal types', function (): void {
        expect(FraudSignalType::HighRedemptionVelocity->getCategory())->toBe('velocity')
            ->and(FraudSignalType::RapidCodeAttempts->getCategory())->toBe('velocity')
            ->and(FraudSignalType::UnusualTimePattern->getCategory())->toBe('pattern')
            ->and(FraudSignalType::GeoAnomalyDetected->getCategory())->toBe('pattern')
            ->and(FraudSignalType::OnlyDiscountedPurchases->getCategory())->toBe('behavioral')
            ->and(FraudSignalType::CodeSharingDetected->getCategory())->toBe('code_abuse');
    });

    it('can get default severity for signal types', function (): void {
        expect(FraudSignalType::HighRedemptionVelocity->getDefaultSeverity())->toBe(65)
            ->and(FraudSignalType::UnusualTimePattern->getDefaultSeverity())->toBe(15)
            ->and(FraudSignalType::LeakedCodeUsage->getDefaultSeverity())->toBe(80)
            ->and(FraudSignalType::InvalidCodeBruteforce->getDefaultSeverity())->toBe(70);
    });

    it('can get velocity signals', function (): void {
        $velocitySignals = FraudSignalType::velocitySignals();
        
        expect($velocitySignals)->toHaveCount(4)
            ->and($velocitySignals)->toContain(FraudSignalType::HighRedemptionVelocity)
            ->and($velocitySignals)->toContain(FraudSignalType::MultipleAccountsAttempt)
            ->and($velocitySignals)->toContain(FraudSignalType::RapidCodeAttempts)
            ->and($velocitySignals)->toContain(FraudSignalType::BurstRedemptions);
    });

    it('can get pattern signals', function (): void {
        $patternSignals = FraudSignalType::patternSignals();
        
        expect($patternSignals)->toHaveCount(5)
            ->and($patternSignals)->toContain(FraudSignalType::UnusualTimePattern)
            ->and($patternSignals)->toContain(FraudSignalType::GeoAnomalyDetected);
    });

    it('can get behavioral signals', function (): void {
        $behavioralSignals = FraudSignalType::behavioralSignals();
        
        expect($behavioralSignals)->toHaveCount(5)
            ->and($behavioralSignals)->toContain(FraudSignalType::OnlyDiscountedPurchases)
            ->and($behavioralSignals)->toContain(FraudSignalType::HighRefundRate);
    });

    it('can get code abuse signals', function (): void {
        $codeAbuseSignals = FraudSignalType::codeAbuseSignals();
        
        expect($codeAbuseSignals)->toHaveCount(5)
            ->and($codeAbuseSignals)->toContain(FraudSignalType::CodeSharingDetected)
            ->and($codeAbuseSignals)->toContain(FraudSignalType::LeakedCodeUsage);
    });

    it('can get signals by category', function (): void {
        $velocityByCategory = FraudSignalType::byCategory('velocity');
        $patternByCategory = FraudSignalType::byCategory('pattern');
        
        expect($velocityByCategory)->toHaveCount(4)
            ->and($patternByCategory)->toHaveCount(5);
    });
});

describe('FraudRiskLevel', function (): void {
    it('can get label for all risk levels', function (): void {
        expect(FraudRiskLevel::Low->getLabel())->toBe('Low Risk')
            ->and(FraudRiskLevel::Medium->getLabel())->toBe('Medium Risk')
            ->and(FraudRiskLevel::High->getLabel())->toBe('High Risk')
            ->and(FraudRiskLevel::Critical->getLabel())->toBe('Critical Risk');
    });

    it('can get color for all risk levels', function (): void {
        expect(FraudRiskLevel::Low->getColor())->toBe('success')
            ->and(FraudRiskLevel::Medium->getColor())->toBe('warning')
            ->and(FraudRiskLevel::High->getColor())->toBe('danger')
            ->and(FraudRiskLevel::Critical->getColor())->toBe('danger');
    });

    it('can determine if should block', function (): void {
        expect(FraudRiskLevel::Low->shouldBlock())->toBeFalse()
            ->and(FraudRiskLevel::Medium->shouldBlock())->toBeFalse()
            ->and(FraudRiskLevel::High->shouldBlock())->toBeTrue()
            ->and(FraudRiskLevel::Critical->shouldBlock())->toBeTrue();
    });

    it('can determine if requires review', function (): void {
        expect(FraudRiskLevel::Low->requiresReview())->toBeFalse()
            ->and(FraudRiskLevel::Medium->requiresReview())->toBeTrue()
            ->and(FraudRiskLevel::High->requiresReview())->toBeTrue()
            ->and(FraudRiskLevel::Critical->requiresReview())->toBeTrue();
    });

    it('can get score ranges', function (): void {
        expect(FraudRiskLevel::Low->getMinScore())->toBe(0.0)
            ->and(FraudRiskLevel::Low->getMaxScore())->toBe(0.3)
            ->and(FraudRiskLevel::Medium->getMinScore())->toBe(0.3)
            ->and(FraudRiskLevel::Medium->getMaxScore())->toBe(0.6)
            ->and(FraudRiskLevel::High->getMinScore())->toBe(0.6)
            ->and(FraudRiskLevel::High->getMaxScore())->toBe(0.8)
            ->and(FraudRiskLevel::Critical->getMinScore())->toBe(0.8)
            ->and(FraudRiskLevel::Critical->getMaxScore())->toBe(1.0);
    });

    it('can check if contains score', function (): void {
        expect(FraudRiskLevel::Low->containsScore(0.0))->toBeTrue()
            ->and(FraudRiskLevel::Low->containsScore(0.29))->toBeTrue()
            ->and(FraudRiskLevel::Low->containsScore(0.3))->toBeFalse()
            ->and(FraudRiskLevel::Medium->containsScore(0.3))->toBeTrue()
            ->and(FraudRiskLevel::Medium->containsScore(0.59))->toBeTrue()
            ->and(FraudRiskLevel::Critical->containsScore(0.8))->toBeTrue()
            ->and(FraudRiskLevel::Critical->containsScore(0.99))->toBeTrue();
    });

    it('can determine risk level from score', function (): void {
        expect(FraudRiskLevel::fromScore(0.0))->toBe(FraudRiskLevel::Low)
            ->and(FraudRiskLevel::fromScore(0.15))->toBe(FraudRiskLevel::Low)
            ->and(FraudRiskLevel::fromScore(0.3))->toBe(FraudRiskLevel::Medium)
            ->and(FraudRiskLevel::fromScore(0.5))->toBe(FraudRiskLevel::Medium)
            ->and(FraudRiskLevel::fromScore(0.6))->toBe(FraudRiskLevel::High)
            ->and(FraudRiskLevel::fromScore(0.75))->toBe(FraudRiskLevel::High)
            ->and(FraudRiskLevel::fromScore(0.8))->toBe(FraudRiskLevel::Critical)
            ->and(FraudRiskLevel::fromScore(1.0))->toBe(FraudRiskLevel::Critical);
    });

    it('can get recommended action', function (): void {
        expect(FraudRiskLevel::Low->getRecommendedAction())->toBe('allow')
            ->and(FraudRiskLevel::Medium->getRecommendedAction())->toBe('flag_for_review')
            ->and(FraudRiskLevel::High->getRecommendedAction())->toBe('require_verification')
            ->and(FraudRiskLevel::Critical->getRecommendedAction())->toBe('block');
    });

    it('can get blocking levels', function (): void {
        $blockingLevels = FraudRiskLevel::blockingLevels();
        
        expect($blockingLevels)->toHaveCount(2)
            ->and($blockingLevels)->toContain(FraudRiskLevel::High)
            ->and($blockingLevels)->toContain(FraudRiskLevel::Critical);
    });

    it('can get review required levels', function (): void {
        $reviewLevels = FraudRiskLevel::reviewRequiredLevels();
        
        expect($reviewLevels)->toHaveCount(3)
            ->and($reviewLevels)->toContain(FraudRiskLevel::Medium)
            ->and($reviewLevels)->toContain(FraudRiskLevel::High)
            ->and($reviewLevels)->toContain(FraudRiskLevel::Critical);
    });
});
