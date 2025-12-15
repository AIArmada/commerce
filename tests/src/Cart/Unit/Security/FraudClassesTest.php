<?php

declare(strict_types=1);

use AIArmada\Cart\Security\Fraud\DetectorResult;
use AIArmada\Cart\Security\Fraud\FraudAnalysisResult;
use AIArmada\Cart\Security\Fraud\FraudSignal;

describe('FraudSignal', function (): void {
    it('can be instantiated with all parameters', function (): void {
        $signal = new FraudSignal(
            type: 'velocity',
            detector: 'VelocityAnalyzer',
            score: 75,
            message: 'High velocity detected',
            recommendation: 'Review transaction',
            metadata: ['actions_per_minute' => 50]
        );

        expect($signal->type)->toBe('velocity')
            ->and($signal->detector)->toBe('VelocityAnalyzer')
            ->and($signal->score)->toBe(75)
            ->and($signal->message)->toBe('High velocity detected')
            ->and($signal->recommendation)->toBe('Review transaction')
            ->and($signal->metadata)->toBe(['actions_per_minute' => 50]);
    });

    it('creates high severity signal with score 80', function (): void {
        $signal = FraudSignal::high(
            type: 'price_manipulation',
            detector: 'PriceDetector',
            message: 'Price manipulation detected'
        );

        expect($signal->score)->toBe(80)
            ->and($signal->type)->toBe('price_manipulation')
            ->and($signal->detector)->toBe('PriceDetector');
    });

    it('creates medium severity signal with score 50', function (): void {
        $signal = FraudSignal::medium(
            type: 'suspicious_behavior',
            detector: 'BehaviorDetector',
            message: 'Suspicious behavior detected'
        );

        expect($signal->score)->toBe(50);
    });

    it('creates low severity signal with score 25', function (): void {
        $signal = FraudSignal::low(
            type: 'minor_anomaly',
            detector: 'AnomalyDetector',
            message: 'Minor anomaly detected'
        );

        expect($signal->score)->toBe(25);
    });

    it('converts to array correctly', function (): void {
        $signal = new FraudSignal(
            type: 'test',
            detector: 'TestDetector',
            score: 60,
            message: 'Test message',
            recommendation: 'Take action',
            metadata: ['key' => 'value']
        );

        $array = $signal->toArray();

        expect($array)->toBe([
            'type' => 'test',
            'detector' => 'TestDetector',
            'score' => 60,
            'message' => 'Test message',
            'recommendation' => 'Take action',
            'metadata' => ['key' => 'value'],
        ]);
    });
});

describe('DetectorResult', function (): void {
    it('can be instantiated', function (): void {
        $signal = new FraudSignal('test', 'Detector', 50, 'Test');

        $result = new DetectorResult(
            detector: 'TestDetector',
            signals: [$signal],
            passed: false,
            executionTimeMs: 100,
            debugInfo: ['info' => 'debug']
        );

        expect($result->detector)->toBe('TestDetector')
            ->and($result->signals)->toHaveCount(1)
            ->and($result->passed)->toBeFalse()
            ->and($result->executionTimeMs)->toBe(100)
            ->and($result->debugInfo)->toBe(['info' => 'debug']);
    });

    it('creates passing result', function (): void {
        $result = DetectorResult::pass('CleanDetector', 50);

        expect($result->detector)->toBe('CleanDetector')
            ->and($result->passed)->toBeTrue()
            ->and($result->signals)->toBeEmpty()
            ->and($result->executionTimeMs)->toBe(50);
    });

    it('creates result with signals', function (): void {
        $signal = new FraudSignal('test', 'Detector', 60, 'Test');

        $result = DetectorResult::withSignals(
            detector: 'SignalDetector',
            signals: [$signal],
            executionTimeMs: 75,
            debugInfo: ['check' => 'passed']
        );

        expect($result->detector)->toBe('SignalDetector')
            ->and($result->signals)->toHaveCount(1)
            ->and($result->passed)->toBeFalse()
            ->and($result->executionTimeMs)->toBe(75);
    });

    it('passes when signals are empty', function (): void {
        $result = DetectorResult::withSignals('EmptyDetector', []);

        expect($result->passed)->toBeTrue();
    });

    it('calculates total score from signals', function (): void {
        $signal1 = new FraudSignal('a', 'D', 30, 'Test');
        $signal2 = new FraudSignal('b', 'D', 20, 'Test');
        $signal3 = new FraudSignal('c', 'D', 50, 'Test');

        $result = DetectorResult::withSignals('Multi', [$signal1, $signal2, $signal3]);

        expect($result->getTotalScore())->toBe(100);
    });

    it('returns zero total score for empty signals', function (): void {
        $result = DetectorResult::pass('Empty');

        expect($result->getTotalScore())->toBe(0);
    });

    it('gets highest severity signal', function (): void {
        $signal1 = new FraudSignal('a', 'D', 30, 'Low');
        $signal2 = new FraudSignal('b', 'D', 80, 'High');
        $signal3 = new FraudSignal('c', 'D', 50, 'Medium');

        $result = DetectorResult::withSignals('Multi', [$signal1, $signal2, $signal3]);

        $highest = $result->getHighestSeveritySignal();
        expect($highest)->toBe($signal2);
    });

    it('returns null for highest severity when no signals', function (): void {
        $result = DetectorResult::pass('Empty');

        expect($result->getHighestSeveritySignal())->toBeNull();
    });

    it('converts to array correctly', function (): void {
        $signal = new FraudSignal('test', 'D', 45, 'Test signal');

        $result = DetectorResult::withSignals('ArrayDetector', [$signal], 100);

        $array = $result->toArray();

        expect($array['detector'])->toBe('ArrayDetector')
            ->and($array['passed'])->toBeFalse()
            ->and($array['signal_count'])->toBe(1)
            ->and($array['total_score'])->toBe(45)
            ->and($array['execution_time_ms'])->toBe(100)
            ->and($array['signals'])->toHaveCount(1);
    });
});

describe('FraudAnalysisResult', function (): void {
    it('can be instantiated', function (): void {
        $result = new FraudAnalysisResult(
            score: 45,
            riskLevel: 'medium',
            signals: [],
            detectorResults: [],
            shouldBlock: false,
            shouldReview: true,
            recommendations: ['Review transaction']
        );

        expect($result->score)->toBe(45)
            ->and($result->riskLevel)->toBe('medium')
            ->and($result->shouldBlock)->toBeFalse()
            ->and($result->shouldReview)->toBeTrue()
            ->and($result->recommendations)->toBe(['Review transaction']);
    });

    it('detects clean transaction', function (): void {
        $result = new FraudAnalysisResult(
            score: 0,
            riskLevel: 'minimal',
            signals: [],
            detectorResults: [],
            shouldBlock: false,
            shouldReview: false,
            recommendations: []
        );

        expect($result->isClean())->toBeTrue();
    });

    it('is not clean when risk level is not minimal', function (): void {
        $result = new FraudAnalysisResult(
            score: 25,
            riskLevel: 'low',
            signals: [],
            detectorResults: [],
            shouldBlock: false,
            shouldReview: false,
            recommendations: []
        );

        expect($result->isClean())->toBeFalse();
    });

    it('is not clean when signals exist', function (): void {
        $signal = new FraudSignal('test', 'D', 10, 'Minor issue');

        $result = new FraudAnalysisResult(
            score: 5,
            riskLevel: 'minimal',
            signals: [$signal],
            detectorResults: [],
            shouldBlock: false,
            shouldReview: false,
            recommendations: []
        );

        expect($result->isClean())->toBeFalse();
    });

    it('groups signals by detector', function (): void {
        $signal1 = new FraudSignal('a', 'Detector1', 30, 'Test');
        $signal2 = new FraudSignal('b', 'Detector2', 40, 'Test');
        $signal3 = new FraudSignal('c', 'Detector1', 50, 'Test');

        $result = new FraudAnalysisResult(
            score: 60,
            riskLevel: 'medium',
            signals: [$signal1, $signal2, $signal3],
            detectorResults: [],
            shouldBlock: false,
            shouldReview: true,
            recommendations: []
        );

        $grouped = $result->getSignalsByDetector();

        expect($grouped)->toHaveCount(2)
            ->and($grouped['Detector1'])->toHaveCount(2)
            ->and($grouped['Detector2'])->toHaveCount(1);
    });

    it('gets high score signals', function (): void {
        $signal1 = new FraudSignal('a', 'D', 30, 'Low');
        $signal2 = new FraudSignal('b', 'D', 60, 'High');
        $signal3 = new FraudSignal('c', 'D', 80, 'Very high');

        $result = new FraudAnalysisResult(
            score: 85,
            riskLevel: 'high',
            signals: [$signal1, $signal2, $signal3],
            detectorResults: [],
            shouldBlock: true,
            shouldReview: true,
            recommendations: []
        );

        $highScore = $result->getHighScoreSignals(50);

        expect($highScore)->toHaveCount(2);
    });

    it('converts to array correctly', function (): void {
        $signal = new FraudSignal('test', 'D', 45, 'Test signal');

        $result = new FraudAnalysisResult(
            score: 45,
            riskLevel: 'medium',
            signals: [$signal],
            detectorResults: [],
            shouldBlock: false,
            shouldReview: true,
            recommendations: ['Review this']
        );

        $array = $result->toArray();

        expect($array['score'])->toBe(45)
            ->and($array['risk_level'])->toBe('medium')
            ->and($array['signal_count'])->toBe(1)
            ->and($array['should_block'])->toBeFalse()
            ->and($array['should_review'])->toBeTrue()
            ->and($array['recommendations'])->toBe(['Review this'])
            ->and($array['signals'])->toHaveCount(1);
    });
});
