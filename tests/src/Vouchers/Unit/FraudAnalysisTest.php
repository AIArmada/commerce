<?php

declare(strict_types=1);

use AIArmada\Vouchers\Fraud\Enums\FraudRiskLevel;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Fraud\FraudAnalysis;
use AIArmada\Vouchers\Fraud\FraudSignal;

describe('FraudSignal', function (): void {
    it('can create signal with default severity', function (): void {
        $signal = FraudSignal::create(
            type: FraudSignalType::HighRedemptionVelocity,
            message: 'Test message',
            metadata: ['test' => 'data'],
        );

        expect($signal->type)->toBe(FraudSignalType::HighRedemptionVelocity)
            ->and($signal->score)->toBe(65.0) // Default severity for HighRedemptionVelocity
            ->and($signal->message)->toBe('Test message')
            ->and($signal->metadata)->toBe(['test' => 'data']);
    });

    it('can create signal with custom score', function (): void {
        $signal = FraudSignal::withScore(
            type: FraudSignalType::UnusualTimePattern,
            score: 35.0,
            message: 'Custom score message',
        );

        expect($signal->type)->toBe(FraudSignalType::UnusualTimePattern)
            ->and($signal->score)->toBe(35.0)
            ->and($signal->message)->toBe('Custom score message');
    });

    it('clamps score to valid range', function (): void {
        $highSignal = FraudSignal::withScore(
            type: FraudSignalType::LeakedCodeUsage,
            score: 150.0,
            message: 'Too high',
        );

        $lowSignal = FraudSignal::withScore(
            type: FraudSignalType::LeakedCodeUsage,
            score: -10.0,
            message: 'Too low',
        );

        expect($highSignal->score)->toBe(100.0)
            ->and($lowSignal->score)->toBe(0.0);
    });

    it('can get category from signal', function (): void {
        $velocitySignal = FraudSignal::create(FraudSignalType::HighRedemptionVelocity, 'Test');
        $patternSignal = FraudSignal::create(FraudSignalType::UnusualTimePattern, 'Test');
        $behavioralSignal = FraudSignal::create(FraudSignalType::OnlyDiscountedPurchases, 'Test');
        $codeAbuseSignal = FraudSignal::create(FraudSignalType::CodeSharingDetected, 'Test');

        expect($velocitySignal->getCategory())->toBe('velocity')
            ->and($patternSignal->getCategory())->toBe('pattern')
            ->and($behavioralSignal->getCategory())->toBe('behavioral')
            ->and($codeAbuseSignal->getCategory())->toBe('code_abuse');
    });

    it('can determine severity level', function (): void {
        $lowSeverity = FraudSignal::withScore(FraudSignalType::UnusualTimePattern, 30.0, 'Low');
        $highSeverity = FraudSignal::withScore(FraudSignalType::UnusualTimePattern, 60.0, 'High');
        $criticalSeverity = FraudSignal::withScore(FraudSignalType::UnusualTimePattern, 80.0, 'Critical');

        expect($lowSeverity->isHighSeverity())->toBeFalse()
            ->and($lowSeverity->isCriticalSeverity())->toBeFalse()
            ->and($highSeverity->isHighSeverity())->toBeTrue()
            ->and($highSeverity->isCriticalSeverity())->toBeFalse()
            ->and($criticalSeverity->isHighSeverity())->toBeTrue()
            ->and($criticalSeverity->isCriticalSeverity())->toBeTrue();
    });

    it('can convert to array', function (): void {
        $signal = FraudSignal::create(
            type: FraudSignalType::HighRedemptionVelocity,
            message: 'High velocity detected',
            metadata: ['count' => 10],
        );

        $array = $signal->toArray();

        expect($array)->toHaveKeys(['type', 'label', 'category', 'score', 'message', 'metadata'])
            ->and($array['type'])->toBe('high_redemption_velocity')
            ->and($array['label'])->toBe('High Redemption Velocity')
            ->and($array['category'])->toBe('velocity')
            ->and($array['score'])->toBe(65.0)
            ->and($array['message'])->toBe('High velocity detected')
            ->and($array['metadata'])->toBe(['count' => 10]);
    });
});

describe('FraudAnalysis', function (): void {
    it('can create clean analysis', function (): void {
        $analysis = FraudAnalysis::clean();

        expect($analysis->fraudScore)->toBe(0.0)
            ->and($analysis->riskLevel)->toBe(FraudRiskLevel::Low)
            ->and($analysis->signals)->toBe([])
            ->and($analysis->shouldBlock)->toBeFalse()
            ->and($analysis->blockReason)->toBeNull()
            ->and($analysis->requiresReview)->toBeFalse()
            ->and($analysis->isClean())->toBeTrue();
    });

    it('can create analysis from signals', function (): void {
        $signals = [
            FraudSignal::withScore(FraudSignalType::HighRedemptionVelocity, 40.0, 'Signal 1'),
            FraudSignal::withScore(FraudSignalType::UnusualTimePattern, 20.0, 'Signal 2'),
        ];

        $analysis = FraudAnalysis::fromSignals($signals);

        // Total score = 60, normalized = 0.6 (Medium-High boundary)
        expect($analysis->fraudScore)->toBe(0.6)
            ->and($analysis->riskLevel)->toBe(FraudRiskLevel::High)
            ->and($analysis->signals)->toHaveCount(2)
            ->and($analysis->shouldBlock)->toBeFalse() // 0.6 < 0.8 default threshold
            ->and($analysis->isClean())->toBeFalse();
    });

    it('blocks when score exceeds threshold', function (): void {
        $signals = [
            FraudSignal::withScore(FraudSignalType::LeakedCodeUsage, 80.0, 'Leaked code'),
            FraudSignal::withScore(FraudSignalType::InvalidCodeBruteforce, 30.0, 'Brute force'),
        ];

        $analysis = FraudAnalysis::fromSignals($signals);

        // Total score = 110, normalized = 1.0, blocked at 0.8
        expect($analysis->shouldBlock)->toBeTrue()
            ->and($analysis->riskLevel)->toBe(FraudRiskLevel::Critical)
            ->and($analysis->blockReason)->toContain('Leaked Code Usage');
    });

    it('can use custom block threshold', function (): void {
        $signals = [
            FraudSignal::withScore(FraudSignalType::UnusualTimePattern, 50.0, 'Unusual time'),
        ];

        // Score = 0.5, using 0.4 threshold
        $analysis = FraudAnalysis::fromSignals($signals, blockThreshold: 0.4);

        expect($analysis->shouldBlock)->toBeTrue()
            ->and($analysis->fraudScore)->toBe(0.5);
    });

    it('can get highest severity signal', function (): void {
        $signals = [
            FraudSignal::withScore(FraudSignalType::UnusualTimePattern, 20.0, 'Low'),
            FraudSignal::withScore(FraudSignalType::LeakedCodeUsage, 80.0, 'High'),
            FraudSignal::withScore(FraudSignalType::HighRedemptionVelocity, 50.0, 'Medium'),
        ];

        $analysis = FraudAnalysis::fromSignals($signals);
        $highest = $analysis->getHighestSeveritySignal();

        expect($highest)->not->toBeNull()
            ->and($highest->type)->toBe(FraudSignalType::LeakedCodeUsage)
            ->and($highest->score)->toBe(80.0);
    });

    it('returns null for highest signal when clean', function (): void {
        $analysis = FraudAnalysis::clean();

        expect($analysis->getHighestSeveritySignal())->toBeNull();
    });

    it('can get signals by category', function (): void {
        $signals = [
            FraudSignal::create(FraudSignalType::HighRedemptionVelocity, 'Velocity 1'),
            FraudSignal::create(FraudSignalType::RapidCodeAttempts, 'Velocity 2'),
            FraudSignal::create(FraudSignalType::UnusualTimePattern, 'Pattern 1'),
            FraudSignal::create(FraudSignalType::CodeSharingDetected, 'Code Abuse 1'),
        ];

        $analysis = FraudAnalysis::fromSignals($signals);
        $grouped = $analysis->getSignalsByCategory();

        expect($grouped)->toHaveKeys(['velocity', 'pattern', 'code_abuse'])
            ->and($grouped['velocity'])->toHaveCount(2)
            ->and($grouped['pattern'])->toHaveCount(1)
            ->and($grouped['code_abuse'])->toHaveCount(1);
    });

    it('can check for specific signal type', function (): void {
        $signals = [
            FraudSignal::create(FraudSignalType::HighRedemptionVelocity, 'Test'),
            FraudSignal::create(FraudSignalType::UnusualTimePattern, 'Test'),
        ];

        $analysis = FraudAnalysis::fromSignals($signals);

        expect($analysis->hasSignal(FraudSignalType::HighRedemptionVelocity))->toBeTrue()
            ->and($analysis->hasSignal(FraudSignalType::UnusualTimePattern))->toBeTrue()
            ->and($analysis->hasSignal(FraudSignalType::LeakedCodeUsage))->toBeFalse();
    });

    it('can get signal count', function (): void {
        $signals = [
            FraudSignal::create(FraudSignalType::HighRedemptionVelocity, 'Test 1'),
            FraudSignal::create(FraudSignalType::UnusualTimePattern, 'Test 2'),
            FraudSignal::create(FraudSignalType::CodeSharingDetected, 'Test 3'),
        ];

        $analysis = FraudAnalysis::fromSignals($signals);

        expect($analysis->getSignalCount())->toBe(3)
            ->and($analysis->hasIssues())->toBeTrue();
    });

    it('can convert to array', function (): void {
        $signals = [
            FraudSignal::withScore(FraudSignalType::HighRedemptionVelocity, 50.0, 'Test'),
        ];

        $analysis = FraudAnalysis::fromSignals($signals);
        $array = $analysis->toArray();

        expect($array)->toHaveKeys([
            'fraud_score',
            'risk_level',
            'signals',
            'should_block',
            'block_reason',
            'requires_review',
            'signal_count',
        ])
            ->and($array['fraud_score'])->toBe(0.5)
            ->and($array['risk_level'])->toBe('medium')
            ->and($array['signals'])->toHaveCount(1)
            ->and($array['signal_count'])->toBe(1);
    });

    it('summarizes top signals in block reason', function (): void {
        $signals = [
            FraudSignal::withScore(FraudSignalType::LeakedCodeUsage, 85.0, 'Leaked'),
            FraudSignal::withScore(FraudSignalType::InvalidCodeBruteforce, 70.0, 'Brute force'),
            FraudSignal::withScore(FraudSignalType::HighRedemptionVelocity, 60.0, 'High velocity'),
            FraudSignal::withScore(FraudSignalType::UnusualTimePattern, 20.0, 'Unusual time'),
            FraudSignal::withScore(FraudSignalType::CodeSharingDetected, 15.0, 'Sharing'),
        ];

        $analysis = FraudAnalysis::fromSignals($signals);

        expect($analysis->shouldBlock)->toBeTrue()
            ->and($analysis->blockReason)->toContain('Leaked Code Usage')
            ->and($analysis->blockReason)->toContain('Invalid Code Bruteforce')
            ->and($analysis->blockReason)->toContain('High Redemption Velocity')
            ->and($analysis->blockReason)->toContain('and 2 more');
    });
});
