<?php

declare(strict_types=1);

use AIArmada\Vouchers\Fraud\Detectors\VelocityDetector;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Fraud\FraudDetectorResult;
use Illuminate\Database\Eloquent\Model;

describe('VelocityDetector comprehensive tests', function (): void {
    beforeEach(function (): void {
        $this->detector = new VelocityDetector;
    });

    describe('threshold configuration', function (): void {
        it('can set redemptions_per_minute threshold', function (): void {
            $result = $this->detector->setThresholds([
                'redemptions_per_minute' => 10,
            ]);

            expect($result)->toBe($this->detector);
        });

        it('can set redemptions_per_hour threshold', function (): void {
            $result = $this->detector->setThresholds([
                'redemptions_per_hour' => 50,
            ]);

            expect($result)->toBe($this->detector);
        });

        it('can set max_accounts_per_code_per_hour threshold', function (): void {
            $result = $this->detector->setThresholds([
                'max_accounts_per_code_per_hour' => 10,
            ]);

            expect($result)->toBe($this->detector);
        });

        it('can set code_attempts_per_minute threshold', function (): void {
            $result = $this->detector->setThresholds([
                'code_attempts_per_minute' => 15,
            ]);

            expect($result)->toBe($this->detector);
        });

        it('can set burst_window_seconds threshold', function (): void {
            $result = $this->detector->setThresholds([
                'burst_window_seconds' => 60,
            ]);

            expect($result)->toBe($this->detector);
        });

        it('can set burst_threshold', function (): void {
            $result = $this->detector->setThresholds([
                'burst_threshold' => 5,
            ]);

            expect($result)->toBe($this->detector);
        });

        it('can set multiple thresholds at once', function (): void {
            $result = $this->detector->setThresholds([
                'redemptions_per_minute' => 10,
                'redemptions_per_hour' => 50,
                'max_accounts_per_code_per_hour' => 5,
                'code_attempts_per_minute' => 15,
                'burst_window_seconds' => 45,
                'burst_threshold' => 4,
            ]);

            expect($result)->toBe($this->detector);
        });

        it('ignores unknown thresholds', function (): void {
            $result = $this->detector->setThresholds([
                'unknown_threshold' => 100,
            ]);

            expect($result)->toBe($this->detector);
        });
    });

    describe('detector properties', function (): void {
        it('has correct name', function (): void {
            expect($this->detector->getName())->toBe('velocity');
        });

        it('has correct category', function (): void {
            expect($this->detector->getCategory())->toBe('velocity');
        });

        it('is enabled by default', function (): void {
            expect($this->detector->isEnabled())->toBeTrue();
        });

        it('can be disabled', function (): void {
            $this->detector->setEnabled(false);

            expect($this->detector->isEnabled())->toBeFalse();
        });

        it('can be re-enabled', function (): void {
            $this->detector->setEnabled(false);
            $this->detector->setEnabled(true);

            expect($this->detector->isEnabled())->toBeTrue();
        });
    });

    describe('detection with disabled state', function (): void {
        it('returns clean result when disabled', function (): void {
            $this->detector->setEnabled(false);

            $cart = new stdClass;
            $result = $this->detector->detect('TEST-CODE', $cart);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class)
                ->and($result->hasSignals())->toBeFalse()
                ->and($result->detector)->toBe('velocity');
        });
    });

    describe('detection with context', function (): void {
        it('accepts context with ip_address', function (): void {
            $cart = new stdClass;
            $context = [
                'ip_address' => '192.168.1.1',
            ];

            $result = $this->detector->detect('TEST-CODE', $cart, null, $context);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);
        });

        it('accepts context with user model', function (): void {
            $cart = new stdClass;
            $user = Mockery::mock(Model::class);
            $user->shouldReceive('getKey')->andReturn('user-123');

            $context = [
                'ip_address' => '192.168.1.1',
            ];

            $result = $this->detector->detect('TEST-CODE', $cart, $user, $context);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);
        });

        it('handles null user gracefully', function (): void {
            $cart = new stdClass;

            $result = $this->detector->detect('TEST-CODE', $cart, null, []);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);
        });

        it('handles empty context gracefully', function (): void {
            $cart = new stdClass;

            $result = $this->detector->detect('TEST-CODE', $cart, null, []);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);
        });
    });

    describe('execution time tracking', function (): void {
        it('tracks execution time', function (): void {
            $cart = new stdClass;

            $result = $this->detector->detect('TEST-CODE', $cart);

            expect($result->executionTimeMs)->toBeGreaterThanOrEqual(0);
        });
    });

    describe('result structure', function (): void {
        it('returns result with correct detector name', function (): void {
            $cart = new stdClass;

            $result = $this->detector->detect('TEST-CODE', $cart);

            expect($result->detector)->toBe('velocity');
        });

        it('returns result that can be converted to array', function (): void {
            $cart = new stdClass;

            $result = $this->detector->detect('TEST-CODE', $cart);
            $array = $result->toArray();

            expect($array)->toHaveKeys([
                'detector',
                'signals',
                'signal_count',
                'total_score',
                'execution_time_ms',
            ]);
        });
    });

    describe('signal types', function (): void {
        it('uses HighRedemptionVelocity signal type', function (): void {
            expect(FraudSignalType::HighRedemptionVelocity->value)->toBe('high_redemption_velocity');
        });

        it('uses MultipleAccountsAttempt signal type', function (): void {
            expect(FraudSignalType::MultipleAccountsAttempt->value)->toBe('multiple_accounts_attempt');
        });

        it('uses RapidCodeAttempts signal type', function (): void {
            expect(FraudSignalType::RapidCodeAttempts->value)->toBe('rapid_code_attempts');
        });

        it('uses BurstRedemptions signal type', function (): void {
            expect(FraudSignalType::BurstRedemptions->value)->toBe('burst_redemptions');
        });
    });
});

describe('VelocityDetector edge cases', function (): void {
    beforeEach(function (): void {
        $this->detector = new VelocityDetector;
    });

    it('handles cart as stdClass', function (): void {
        $cart = new stdClass;
        $cart->id = 'cart-123';

        $result = $this->detector->detect('TEST-CODE', $cart);

        expect($result)->toBeInstanceOf(FraudDetectorResult::class);
    });

    it('handles various code formats', function (): void {
        $cart = new stdClass;

        $codes = [
            'SIMPLE',
            'with-dashes',
            'with_underscores',
            'MixedCase123',
            '12345',
            'DISCOUNT-2024-SUMMER',
        ];

        foreach ($codes as $code) {
            $result = $this->detector->detect($code, $cart);
            expect($result)->toBeInstanceOf(FraudDetectorResult::class);
        }
    });

    it('handles empty code', function (): void {
        $cart = new stdClass;

        $result = $this->detector->detect('', $cart);

        expect($result)->toBeInstanceOf(FraudDetectorResult::class);
    });

    it('handles very long code', function (): void {
        $cart = new stdClass;
        $longCode = str_repeat('A', 255);

        $result = $this->detector->detect($longCode, $cart);

        expect($result)->toBeInstanceOf(FraudDetectorResult::class);
    });
});
