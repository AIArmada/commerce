<?php

declare(strict_types=1);

use AIArmada\Vouchers\Fraud\Detectors\BehavioralDetector;
use AIArmada\Vouchers\Fraud\Detectors\CodeAbuseDetector;
use AIArmada\Vouchers\Fraud\Detectors\PatternDetector;
use AIArmada\Vouchers\Fraud\Detectors\VelocityDetector;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Fraud\FraudDetectorResult;

describe('VelocityDetector', function (): void {
    it('has correct name and category', function (): void {
        $detector = new VelocityDetector();

        expect($detector->getName())->toBe('velocity')
            ->and($detector->getCategory())->toBe('velocity');
    });

    it('is enabled by default', function (): void {
        $detector = new VelocityDetector();

        expect($detector->isEnabled())->toBeTrue();
    });

    it('can be disabled', function (): void {
        $detector = new VelocityDetector();
        $detector->setEnabled(false);

        expect($detector->isEnabled())->toBeFalse();
    });

    it('returns clean result when disabled', function (): void {
        $detector = new VelocityDetector();
        $detector->setEnabled(false);

        $cart = new stdClass();
        $result = $detector->detect('TEST-CODE', $cart);

        expect($result)->toBeInstanceOf(FraudDetectorResult::class)
            ->and($result->hasSignals())->toBeFalse()
            ->and($result->detector)->toBe('velocity');
    });

    it('returns result with execution time', function (): void {
        $detector = new VelocityDetector();
        $cart = new stdClass();

        $result = $detector->detect('TEST-CODE', $cart);

        expect($result->executionTimeMs)->toBeGreaterThanOrEqual(0);
    });

    it('can configure thresholds', function (): void {
        $detector = new VelocityDetector();
        $detector->setThresholds([
            'redemptions_per_minute' => 10,
            'redemptions_per_hour' => 50,
            'max_accounts_per_code_per_hour' => 5,
        ]);

        // Detector should use new thresholds (internal state)
        expect($detector->isEnabled())->toBeTrue();
    });
});

describe('PatternDetector', function (): void {
    it('has correct name and category', function (): void {
        $detector = new PatternDetector();

        expect($detector->getName())->toBe('pattern')
            ->and($detector->getCategory())->toBe('pattern');
    });

    it('detects unusual time patterns', function (): void {
        $detector = new PatternDetector();
        $cart = new stdClass();
        $currentHour = (int) date('G');

        // Force the detector to treat the current hour as unusual so the assertion always runs
        $detector->setUnusualHours([$currentHour]);

        $result = $detector->detect('TEST-CODE', $cart, null, []);

        expect($result->hasSignals())->toBeTrue();
        $signal = $result->signals[0];
        expect($signal->type)->toBe(FraudSignalType::UnusualTimePattern);
    });

    it('detects geo anomalies for impossible travel', function (): void {
        $detector = new PatternDetector();
        $cart = new stdClass();

        // Simulate impossible travel: NY to Tokyo in 1 hour
        $context = [
            'geo_location' => ['lat' => 35.6762, 'lng' => 139.6503], // Tokyo
            'previous_geo_location' => ['lat' => 40.7128, 'lng' => -74.0060], // New York
            'time_since_last_transaction_seconds' => 3600, // 1 hour
        ];

        $result = $detector->detect('TEST-CODE', $cart, null, $context);

        // Distance ~10,800 km in 1 hour = 10,800 km/h (impossible)
        expect($result->hasSignals())->toBeTrue();

        $geoSignal = collect($result->signals)->first(
            fn ($s) => $s->type === FraudSignalType::GeoAnomalyDetected
        );
        expect($geoSignal)->not->toBeNull();
    });

    it('detects device fingerprint mismatch', function (): void {
        $detector = new PatternDetector();
        $cart = new stdClass();

        $context = [
            'device_fingerprint' => 'unknown-fingerprint-123',
            'known_device_fingerprints' => ['known-fp-1', 'known-fp-2'],
        ];

        $result = $detector->detect('TEST-CODE', $cart, null, $context);

        expect($result->hasSignals())->toBeTrue();

        $fpSignal = collect($result->signals)->first(
            fn ($s) => $s->type === FraudSignalType::DeviceFingerprintMismatch
        );
        expect($fpSignal)->not->toBeNull();
    });

    it('detects IP proxy/VPN usage', function (): void {
        $detector = new PatternDetector();
        $cart = new stdClass();

        $context = [
            'ip_address' => '192.168.1.1',
            'is_vpn' => true,
        ];

        $result = $detector->detect('TEST-CODE', $cart, null, $context);

        expect($result->hasSignals())->toBeTrue();

        $ipSignal = collect($result->signals)->first(
            fn ($s) => $s->type === FraudSignalType::IpAddressAnomaly
        );
        expect($ipSignal)->not->toBeNull()
            ->and($ipSignal->message)->toContain('VPN');
    });

    it('detects session anomaly', function (): void {
        $detector = new PatternDetector();
        $cart = new stdClass();

        $context = [
            'session_id' => 'new-session-123',
            'previous_session_id' => 'old-session-456',
            'was_reauthenticated' => false,
        ];

        $result = $detector->detect('TEST-CODE', $cart, null, $context);

        expect($result->hasSignals())->toBeTrue();

        $sessionSignal = collect($result->signals)->first(
            fn ($s) => $s->type === FraudSignalType::SessionAnomaly
        );
        expect($sessionSignal)->not->toBeNull();
    });
});

describe('BehavioralDetector', function (): void {
    it('has correct name and category', function (): void {
        $detector = new BehavioralDetector();

        expect($detector->getName())->toBe('behavioral')
            ->and($detector->getCategory())->toBe('behavioral');
    });

    it('detects discount-only purchase pattern', function (): void {
        $detector = new BehavioralDetector();
        $cart = new stdClass();
        $user = Mockery::mock(Illuminate\Database\Eloquent\Model::class);
        $user->shouldReceive('getKey')->andReturn('user-123');

        $context = [
            'user_total_orders' => 10,
            'user_discounted_orders' => 10, // 100% discounted
        ];

        $result = $detector->detect('TEST-CODE', $cart, $user, $context);

        expect($result->hasSignals())->toBeTrue();

        $discountSignal = collect($result->signals)->first(
            fn ($s) => $s->type === FraudSignalType::OnlyDiscountedPurchases
        );
        expect($discountSignal)->not->toBeNull();
    });

    it('detects high refund rate', function (): void {
        $detector = new BehavioralDetector();
        $cart = new stdClass();
        $user = Mockery::mock(Illuminate\Database\Eloquent\Model::class);
        $user->shouldReceive('getKey')->andReturn('user-123');

        $context = [
            'user_total_orders' => 10,
            'user_refunded_orders' => 5, // 50% refund rate
        ];

        $result = $detector->detect('TEST-CODE', $cart, $user, $context);

        expect($result->hasSignals())->toBeTrue();

        $refundSignal = collect($result->signals)->first(
            fn ($s) => $s->type === FraudSignalType::HighRefundRate
        );
        expect($refundSignal)->not->toBeNull();
    });

    it('detects cart manipulation', function (): void {
        $detector = new BehavioralDetector();
        $cart = new stdClass();

        $context = [
            'cart_modification_count' => 15, // Excessive modifications
            'coupon_add_remove_count' => 10, // Coupon cycling
        ];

        $result = $detector->detect('TEST-CODE', $cart, null, $context);

        expect($result->hasSignals())->toBeTrue();

        $cartSignals = collect($result->signals)->filter(
            fn ($s) => $s->type === FraudSignalType::CartManipulation
        );
        expect($cartSignals)->toHaveCount(2);
    });

    it('detects suspicious checkout pattern', function (): void {
        $detector = new BehavioralDetector();
        $cart = new stdClass();

        $context = [
            'checkout_attempt_count' => 10,
            'payment_failure_count' => 5,
        ];

        $result = $detector->detect('TEST-CODE', $cart, null, $context);

        expect($result->hasSignals())->toBeTrue();

        $checkoutSignals = collect($result->signals)->filter(
            fn ($s) => $s->type === FraudSignalType::SuspiciousCheckoutPattern
        );
        expect($checkoutSignals->count())->toBeGreaterThanOrEqual(2);
    });

    it('detects abnormally high cart value', function (): void {
        $detector = new BehavioralDetector();
        $cart = new class
        {
            public function getTotal(): float
            {
                return 15000.0;
            }
        };

        $result = $detector->detect('TEST-CODE', $cart, null, []);

        expect($result->hasSignals())->toBeTrue();

        $cartValueSignal = collect($result->signals)->first(
            fn ($s) => $s->type === FraudSignalType::AbnormalCartValue
        );
        expect($cartValueSignal)->not->toBeNull()
            ->and($cartValueSignal->metadata['type'])->toBe('high');
    });

    it('detects near-zero cart after discount', function (): void {
        $detector = new BehavioralDetector();
        $cart = new class
        {
            public function getTotal(): float
            {
                return 100.0;
            }
        };

        $context = [
            'discount_amount' => 99.50, // Almost free
        ];

        $result = $detector->detect('TEST-CODE', $cart, null, $context);

        expect($result->hasSignals())->toBeTrue();

        $cartValueSignal = collect($result->signals)->first(
            fn ($s) => $s->type === FraudSignalType::AbnormalCartValue
        );
        expect($cartValueSignal)->not->toBeNull()
            ->and($cartValueSignal->metadata['type'])->toBe('low');
    });
});

describe('CodeAbuseDetector', function (): void {
    it('has correct name and category', function (): void {
        $detector = new CodeAbuseDetector();

        expect($detector->getName())->toBe('code_abuse')
            ->and($detector->getCategory())->toBe('code_abuse');
    });

    it('detects known leaked code usage', function (): void {
        $detector = new CodeAbuseDetector();
        $cart = new stdClass();

        $context = [
            'is_known_leaked_code' => true,
            'leaked_code_source' => 'twitter',
        ];

        $result = $detector->detect('TEST-CODE', $cart, null, $context);

        expect($result->hasSignals())->toBeTrue();

        $leakedSignal = collect($result->signals)->first(
            fn ($s) => $s->type === FraudSignalType::LeakedCodeUsage
        );
        expect($leakedSignal)->not->toBeNull()
            ->and($leakedSignal->score)->toBe(80.0);
    });

    it('detects sequential code attempts', function (): void {
        $detector = new CodeAbuseDetector();
        $cart = new stdClass();

        $context = [
            'recent_code_attempts' => ['CODE1', 'CODE2', 'CODE3', 'CODE4', 'CODE5'],
        ];

        $result = $detector->detect('TEST-CODE', $cart, null, $context);

        expect($result->hasSignals())->toBeTrue();

        $sequentialSignal = collect($result->signals)->first(
            fn ($s) => $s->type === FraudSignalType::SequentialCodeAttempts
        );
        expect($sequentialSignal)->not->toBeNull();
    });

    it('detects brute force attempts', function (): void {
        $detector = new CodeAbuseDetector();
        $cart = new stdClass();

        $context = [
            'recent_invalid_attempts' => 10,
            'invalid_attempt_window_minutes' => 5,
        ];

        $result = $detector->detect('TEST-CODE', $cart, null, $context);

        expect($result->hasSignals())->toBeTrue();

        $bruteForceSignal = collect($result->signals)->first(
            fn ($s) => $s->type === FraudSignalType::InvalidCodeBruteforce
        );
        expect($bruteForceSignal)->not->toBeNull();
    });

    it('detects expired code abuse', function (): void {
        $detector = new CodeAbuseDetector();
        $cart = new stdClass();

        $context = [
            'expired_code_attempts' => 5,
        ];

        $result = $detector->detect('TEST-CODE', $cart, null, $context);

        expect($result->hasSignals())->toBeTrue();

        $expiredSignal = collect($result->signals)->first(
            fn ($s) => $s->type === FraudSignalType::ExpiredCodeAbuse
        );
        expect($expiredSignal)->not->toBeNull();
    });

    it('can configure detector', function (): void {
        $detector = new CodeAbuseDetector();
        $detector->configure([
            'max_unique_ips_per_code' => 10,
            'max_sequential_invalid_attempts' => 10,
            'analysis_window_hours' => 48,
        ]);

        expect($detector->isEnabled())->toBeTrue();
    });
});

describe('FraudDetectorResult', function (): void {
    it('can create clean result', function (): void {
        $result = FraudDetectorResult::clean('velocity', 1.5);

        expect($result->signals)->toBe([])
            ->and($result->detector)->toBe('velocity')
            ->and($result->executionTimeMs)->toBe(1.5)
            ->and($result->hasSignals())->toBeFalse()
            ->and($result->getSignalCount())->toBe(0)
            ->and($result->getTotalScore())->toBe(0.0);
    });

    it('can create result with signals', function (): void {
        $signals = [
            AIArmada\Vouchers\Fraud\FraudSignal::create(
                FraudSignalType::HighRedemptionVelocity,
                'High velocity'
            ),
            AIArmada\Vouchers\Fraud\FraudSignal::withScore(
                FraudSignalType::RapidCodeAttempts,
                50.0,
                'Rapid attempts'
            ),
        ];

        $result = FraudDetectorResult::withSignals($signals, 'velocity', 2.5);

        expect($result->hasSignals())->toBeTrue()
            ->and($result->getSignalCount())->toBe(2)
            ->and($result->detector)->toBe('velocity')
            ->and($result->executionTimeMs)->toBe(2.5);
    });

    it('can get total score', function (): void {
        $signals = [
            AIArmada\Vouchers\Fraud\FraudSignal::withScore(
                FraudSignalType::HighRedemptionVelocity,
                40.0,
                'Signal 1'
            ),
            AIArmada\Vouchers\Fraud\FraudSignal::withScore(
                FraudSignalType::RapidCodeAttempts,
                30.0,
                'Signal 2'
            ),
        ];

        $result = FraudDetectorResult::withSignals($signals, 'velocity');

        expect($result->getTotalScore())->toBe(70.0);
    });

    it('can get highest severity signal', function (): void {
        $signals = [
            AIArmada\Vouchers\Fraud\FraudSignal::withScore(
                FraudSignalType::UnusualTimePattern,
                30.0,
                'Low'
            ),
            AIArmada\Vouchers\Fraud\FraudSignal::withScore(
                FraudSignalType::HighRedemptionVelocity,
                80.0,
                'High'
            ),
            AIArmada\Vouchers\Fraud\FraudSignal::withScore(
                FraudSignalType::RapidCodeAttempts,
                50.0,
                'Medium'
            ),
        ];

        $result = FraudDetectorResult::withSignals($signals, 'velocity');
        $highest = $result->getHighestSeveritySignal();

        expect($highest)->not->toBeNull()
            ->and($highest->score)->toBe(80.0)
            ->and($highest->type)->toBe(FraudSignalType::HighRedemptionVelocity);
    });

    it('returns null for highest signal when empty', function (): void {
        $result = FraudDetectorResult::clean('velocity');

        expect($result->getHighestSeveritySignal())->toBeNull();
    });

    it('can convert to array', function (): void {
        $signals = [
            AIArmada\Vouchers\Fraud\FraudSignal::withScore(
                FraudSignalType::HighRedemptionVelocity,
                60.0,
                'Test signal'
            ),
        ];

        $result = FraudDetectorResult::withSignals($signals, 'velocity', 3.5);
        $array = $result->toArray();

        expect($array)->toHaveKeys([
            'detector',
            'signals',
            'signal_count',
            'total_score',
            'execution_time_ms',
        ])
            ->and($array['detector'])->toBe('velocity')
            ->and($array['signals'])->toHaveCount(1)
            ->and($array['signal_count'])->toBe(1)
            ->and($array['total_score'])->toBe(60.0)
            ->and($array['execution_time_ms'])->toBe(3.5);
    });
});
