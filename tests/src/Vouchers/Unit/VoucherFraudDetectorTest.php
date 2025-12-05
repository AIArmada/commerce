<?php

declare(strict_types=1);

use AIArmada\Vouchers\Fraud\Enums\FraudRiskLevel;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Fraud\VoucherFraudDetector;
use AIArmada\Vouchers\Fraud\Detectors\VelocityDetector;
use AIArmada\Vouchers\Fraud\Detectors\PatternDetector;
use AIArmada\Vouchers\Fraud\Detectors\BehavioralDetector;
use AIArmada\Vouchers\Fraud\Detectors\CodeAbuseDetector;
use AIArmada\Vouchers\Fraud\FraudAnalysis;

describe('VoucherFraudDetector', function (): void {
    it('can be instantiated', function (): void {
        $detector = new VoucherFraudDetector();

        expect($detector)->toBeInstanceOf(VoucherFraudDetector::class);
    });

    it('can be created with make()', function (): void {
        $detector = VoucherFraudDetector::make();

        expect($detector)->toBeInstanceOf(VoucherFraudDetector::class);
    });

    it('registers default detectors', function (): void {
        $detector = new VoucherFraudDetector();
        $detectors = $detector->getDetectors();

        expect($detectors)->toHaveKeys(['velocity', 'pattern', 'behavioral', 'code_abuse'])
            ->and($detectors['velocity'])->toBeInstanceOf(VelocityDetector::class)
            ->and($detectors['pattern'])->toBeInstanceOf(PatternDetector::class)
            ->and($detectors['behavioral'])->toBeInstanceOf(BehavioralDetector::class)
            ->and($detectors['code_abuse'])->toBeInstanceOf(CodeAbuseDetector::class);
    });

    it('can get individual detector', function (): void {
        $detector = new VoucherFraudDetector();

        expect($detector->getDetector('velocity'))->toBeInstanceOf(VelocityDetector::class)
            ->and($detector->getDetector('nonexistent'))->toBeNull();
    });

    it('can register custom detector', function (): void {
        $detector = new VoucherFraudDetector();
        $customDetector = new VelocityDetector();

        $detector->registerDetector('custom', $customDetector);

        expect($detector->getDetector('custom'))->toBe($customDetector);
    });

    it('can remove detector', function (): void {
        $detector = new VoucherFraudDetector();

        $detector->removeDetector('velocity');

        expect($detector->getDetector('velocity'))->toBeNull()
            ->and($detector->getDetectors())->toHaveCount(3);
    });

    it('can enable and disable detectors', function (): void {
        $detector = new VoucherFraudDetector();

        $detector->disableDetector('velocity');
        expect($detector->getDetector('velocity')->isEnabled())->toBeFalse();

        $detector->enableDetector('velocity');
        expect($detector->getDetector('velocity')->isEnabled())->toBeTrue();
    });

    it('can set block threshold', function (): void {
        $detector = new VoucherFraudDetector();

        $detector->setBlockThreshold(0.5);
        expect($detector->getBlockThreshold())->toBe(0.5);

        // Clamps to valid range
        $detector->setBlockThreshold(1.5);
        expect($detector->getBlockThreshold())->toBe(1.0);

        $detector->setBlockThreshold(-0.5);
        expect($detector->getBlockThreshold())->toBe(0.0);
    });

    it('can analyze redemption', function (): void {
        $detector = new VoucherFraudDetector();
        $cart = new stdClass();

        $analysis = $detector->analyze('TEST-CODE', $cart);

        expect($analysis)->toBeInstanceOf(FraudAnalysis::class);
    });

    it('can check if should block', function (): void {
        $detector = new VoucherFraudDetector();
        $cart = new stdClass();

        $shouldBlock = $detector->shouldBlock('TEST-CODE', $cart);

        expect($shouldBlock)->toBeBool();
    });

    it('can get risk level', function (): void {
        $detector = new VoucherFraudDetector();
        $cart = new stdClass();

        $riskLevel = $detector->getRiskLevel('TEST-CODE', $cart);

        expect($riskLevel)->toBeInstanceOf(FraudRiskLevel::class);
    });

    it('aggregates signals from all detectors', function (): void {
        $detector = new VoucherFraudDetector();
        $cart = new class {
            public function getTotal(): float
            {
                return 100.0;
            }
        };

        $user = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
        $user->shouldReceive('getKey')->andReturn('user-123');

        $context = [
            // Pattern detector triggers
            'device_fingerprint' => 'unknown-fp',
            'known_device_fingerprints' => ['known-fp'],
            // Behavioral detector triggers
            'user_total_orders' => 10,
            'user_discounted_orders' => 10,
            // Code abuse detector triggers
            'recent_invalid_attempts' => 10,
            'invalid_attempt_window_minutes' => 5,
        ];

        $analysis = $detector->analyze('TEST-CODE', $cart, $user, $context);

        expect($analysis->hasIssues())->toBeTrue()
            ->and($analysis->getSignalCount())->toBeGreaterThanOrEqual(3);
    });

    it('blocks high-risk redemptions', function (): void {
        $detector = new VoucherFraudDetector();
        $detector->setBlockThreshold(0.5);

        $cart = new class {
            public function getTotal(): float
            {
                return 100.0;
            }
        };

        $context = [
            // Multiple high-severity signals
            'is_known_leaked_code' => true,
            'recent_invalid_attempts' => 20,
            'invalid_attempt_window_minutes' => 5,
            'user_total_orders' => 10,
            'user_refunded_orders' => 8,
        ];

        $user = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
        $user->shouldReceive('getKey')->andReturn('user-123');

        $analysis = $detector->analyze('TEST-CODE', $cart, $user, $context);

        expect($analysis->shouldBlock)->toBeTrue()
            ->and($analysis->riskLevel->shouldBlock())->toBeTrue();
    });

    it('skips disabled detectors', function (): void {
        $detector = new VoucherFraudDetector();
        $detector->disableDetector('velocity')
            ->disableDetector('pattern')
            ->disableDetector('behavioral');

        $cart = new stdClass();
        $context = [
            // Only code abuse should be checked
            'recent_invalid_attempts' => 10,
            'invalid_attempt_window_minutes' => 5,
        ];

        $analysis = $detector->analyze('TEST-CODE', $cart, null, $context);

        // Only code abuse signals should be present
        foreach ($analysis->signals as $signal) {
            expect($signal->getCategory())->toBe('code_abuse');
        }
    });

    it('can configure velocity detector', function (): void {
        $detector = new VoucherFraudDetector();
        $detector->configureVelocityDetector([
            'redemptions_per_minute' => 10,
            'redemptions_per_hour' => 100,
        ]);

        $velocityDetector = $detector->getDetector('velocity');
        expect($velocityDetector)->toBeInstanceOf(VelocityDetector::class);
    });

    it('can configure pattern detector', function (): void {
        $detector = new VoucherFraudDetector();
        $detector->configurePatternDetector(
            unusualHours: [0, 1, 2],
            suspiciousIpPatterns: ['10.0.0.', '192.168.'],
        );

        $patternDetector = $detector->getDetector('pattern');
        expect($patternDetector)->toBeInstanceOf(PatternDetector::class);
    });

    it('can configure behavioral detector', function (): void {
        $detector = new VoucherFraudDetector();
        $detector->configureBehavioralDetector([
            'min_orders_for_analysis' => 3,
            'discount_only_threshold' => 0.8,
        ]);

        $behavioralDetector = $detector->getDetector('behavioral');
        expect($behavioralDetector)->toBeInstanceOf(BehavioralDetector::class);
    });

    it('can configure code abuse detector', function (): void {
        $detector = new VoucherFraudDetector();
        $detector->configureCodeAbuseDetector([
            'max_unique_ips_per_code' => 10,
            'max_sequential_invalid_attempts' => 10,
        ]);

        $codeAbuseDetector = $detector->getDetector('code_abuse');
        expect($codeAbuseDetector)->toBeInstanceOf(CodeAbuseDetector::class);
    });

    it('can configure from array', function (): void {
        $detector = new VoucherFraudDetector();
        $detector->configure([
            'block_threshold' => 0.6,
            'velocity' => [
                'redemptions_per_minute' => 3,
            ],
            'pattern' => [
                'unusual_hours' => [0, 1, 2, 3],
            ],
            'behavioral' => [
                'min_orders_for_analysis' => 5,
            ],
            'code_abuse' => [
                'max_unique_ips_per_code' => 3,
            ],
            'disabled_detectors' => ['pattern'],
        ]);

        expect($detector->getBlockThreshold())->toBe(0.6)
            ->and($detector->getDetector('pattern')->isEnabled())->toBeFalse();
    });
});

describe('VoucherFraudDetector Integration', function (): void {
    it('processes clean redemption correctly', function (): void {
        $detector = VoucherFraudDetector::make();
        $cart = new class {
            public function getTotal(): float
            {
                return 50.0;
            }
        };

        $analysis = $detector->analyze('VALID-CODE', $cart);

        // Either clean or low risk (pattern detector might detect unusual time)
        expect($analysis->riskLevel->value)->toBeIn(['low', 'medium']);
    });

    it('handles minimal context gracefully', function (): void {
        $detector = VoucherFraudDetector::make();
        $cart = new stdClass();

        // No context at all
        $analysis = $detector->analyze('CODE', $cart);

        expect($analysis)->toBeInstanceOf(FraudAnalysis::class);
    });

    it('handles null user gracefully', function (): void {
        $detector = VoucherFraudDetector::make();
        $cart = new stdClass();

        $analysis = $detector->analyze('CODE', $cart, null, [
            'ip_address' => '192.168.1.1',
        ]);

        expect($analysis)->toBeInstanceOf(FraudAnalysis::class);
    });

    it('accumulates signals from multiple categories', function (): void {
        $detector = VoucherFraudDetector::make();
        $cart = new class {
            public function getTotal(): float
            {
                return 15000.0; // Triggers abnormal cart value
            }
        };

        $user = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
        $user->shouldReceive('getKey')->andReturn('user-123');

        $context = [
            // Trigger pattern signal
            'device_fingerprint' => 'unknown',
            'known_device_fingerprints' => ['known-1'],
            // Trigger behavioral signal (cart value already triggers)
            // Trigger code abuse signal
            'expired_code_attempts' => 5,
        ];

        $analysis = $detector->analyze('TEST', $cart, $user, $context);
        $categories = array_keys($analysis->getSignalsByCategory());

        expect($categories)->toContain('pattern')
            ->and($categories)->toContain('behavioral')
            ->and($categories)->toContain('code_abuse');
    });
});
