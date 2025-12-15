<?php

declare(strict_types=1);

use AIArmada\Cart\Security\Fraud\FraudDetectionEngine;
use AIArmada\Cart\Security\Fraud\FraudDetectorInterface;
use AIArmada\Cart\Security\Fraud\FraudSignalCollector;

describe('FraudDetectionEngine', function (): void {
    beforeEach(function (): void {
        $this->signalCollector = new FraudSignalCollector;
        $this->engine = new FraudDetectionEngine($this->signalCollector);
    });

    it('can be instantiated', function (): void {
        expect($this->engine)->toBeInstanceOf(FraudDetectionEngine::class);
    });

    it('registers a detector', function (): void {
        $detector = Mockery::mock(FraudDetectorInterface::class);
        $detector->shouldReceive('getName')->andReturn('MockDetector');

        $this->engine->registerDetector($detector);

        expect($this->engine->getDetectors())->toHaveCount(1);
    });

    it('registers multiple detectors', function (): void {
        $detector1 = Mockery::mock(FraudDetectorInterface::class);
        $detector1->shouldReceive('getName')->andReturn('Detector1');

        $detector2 = Mockery::mock(FraudDetectorInterface::class);
        $detector2->shouldReceive('getName')->andReturn('Detector2');

        $this->engine->registerDetectors([$detector1, $detector2]);

        expect($this->engine->getDetectors())->toHaveCount(2);
    });

    it('returns empty detectors initially', function (): void {
        expect($this->engine->getDetectors())->toBeEmpty();
    });

    it('has threshold constants', function (): void {
        expect(FraudDetectionEngine::THRESHOLD_LOW)->toBe(30)
            ->and(FraudDetectionEngine::THRESHOLD_MEDIUM)->toBe(60)
            ->and(FraudDetectionEngine::THRESHOLD_HIGH)->toBe(80);
    });

    it('can be configured', function (): void {
        $result = $this->engine->configure(['custom_setting' => 'value']);

        expect($result)->toBeInstanceOf(FraudDetectionEngine::class);
    });
});

describe('FraudSignalCollector', function (): void {
    beforeEach(function (): void {
        $this->collector = new FraudSignalCollector;
    });

    it('can be instantiated', function (): void {
        expect($this->collector)->toBeInstanceOf(FraudSignalCollector::class);
    });

    it('returns false for unflagged user', function (): void {
        expect($this->collector->isUserFlagged('unknown-user'))->toBeFalse();
    });

    it('returns zero signal count for unknown user', function (): void {
        $count = $this->collector->getSignalCountForUser('unknown-user-123', 60);

        expect($count)->toBe(0);
    });

    it('returns zero signal count for unknown ip', function (): void {
        $count = $this->collector->getSignalCountForIp('192.168.1.99', 60);

        expect($count)->toBe(0);
    });

    it('returns zero aggregated score for unknown user', function (): void {
        $score = $this->collector->getAggregatedScoreForUser('unknown-user-456', 60);

        expect($score)->toBe(0);
    });

    it('returns empty recent signals for unknown user', function (): void {
        $signals = $this->collector->getRecentSignalsForUser('unknown-user-789');

        expect($signals)->toBeArray()->toBeEmpty();
    });

    it('returns empty recent signals for unknown ip', function (): void {
        $signals = $this->collector->getRecentSignalsForIp('10.0.0.1');

        expect($signals)->toBeArray()->toBeEmpty();
    });

    it('gets statistics', function (): void {
        $stats = $this->collector->getStatistics(24);

        expect($stats)->toBeArray()
            ->and($stats)->toHaveKey('total_signals')
            ->and($stats)->toHaveKey('unique_users')
            ->and($stats)->toHaveKey('unique_ips');
    });
});
