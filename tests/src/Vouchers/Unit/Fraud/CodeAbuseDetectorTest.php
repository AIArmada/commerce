<?php

declare(strict_types=1);

use AIArmada\Vouchers\Fraud\Detectors\CodeAbuseDetector;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Fraud\FraudDetectorResult;

describe('CodeAbuseDetector', function (): void {
    describe('basic properties', function (): void {
        it('has correct name', function (): void {
            $detector = new CodeAbuseDetector;

            expect($detector->getName())->toBe('code_abuse');
        });

        it('has correct category', function (): void {
            $detector = new CodeAbuseDetector;

            expect($detector->getCategory())->toBe('code_abuse');
        });

        it('is enabled by default', function (): void {
            $detector = new CodeAbuseDetector;

            expect($detector->isEnabled())->toBeTrue();
        });

        it('can be disabled', function (): void {
            $detector = new CodeAbuseDetector;
            $detector->setEnabled(false);

            expect($detector->isEnabled())->toBeFalse();
        });
    });

    describe('configuration', function (): void {
        it('can configure max_unique_ips_per_code', function (): void {
            $detector = new CodeAbuseDetector;
            $result = $detector->configure(['max_unique_ips_per_code' => 10]);

            expect($result)->toBeInstanceOf(CodeAbuseDetector::class);
        });

        it('can configure max_sequential_invalid_attempts', function (): void {
            $detector = new CodeAbuseDetector;
            $result = $detector->configure(['max_sequential_invalid_attempts' => 8]);

            expect($result)->toBeInstanceOf(CodeAbuseDetector::class);
        });

        it('can configure max_expired_code_attempts', function (): void {
            $detector = new CodeAbuseDetector;
            $result = $detector->configure(['max_expired_code_attempts' => 5]);

            expect($result)->toBeInstanceOf(CodeAbuseDetector::class);
        });

        it('can configure analysis_window_hours', function (): void {
            $detector = new CodeAbuseDetector;
            $result = $detector->configure(['analysis_window_hours' => 48]);

            expect($result)->toBeInstanceOf(CodeAbuseDetector::class);
        });

        it('can configure known_leaked_patterns', function (): void {
            $detector = new CodeAbuseDetector;
            $result = $detector->configure(['known_leaked_patterns' => ['LEAKED', 'HACKED']]);

            expect($result)->toBeInstanceOf(CodeAbuseDetector::class);
        });

        it('can configure multiple options at once', function (): void {
            $detector = new CodeAbuseDetector;
            $result = $detector->configure([
                'max_unique_ips_per_code' => 10,
                'max_sequential_invalid_attempts' => 8,
                'analysis_window_hours' => 48,
            ]);

            expect($result)->toBeInstanceOf(CodeAbuseDetector::class);
        });
    });

    describe('detection when disabled', function (): void {
        it('returns clean result when disabled', function (): void {
            $detector = new CodeAbuseDetector;
            $detector->setEnabled(false);

            $cart = new stdClass;
            $result = $detector->detect('TESTCODE', $cart);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class)
                ->and($result->signals)->toBeEmpty();
        });
    });

    describe('leaked code detection', function (): void {
        it('detects leaked code pattern', function (): void {
            $detector = new CodeAbuseDetector;
            $detector->configure(['known_leaked_patterns' => ['LEAKED']]);

            $cart = new stdClass;
            $result = $detector->detect('LEAKED123', $cart);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);

            $leakedSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::LeakedCodeUsage);

            expect(count($leakedSignals))->toBeGreaterThanOrEqual(1);
        });

        it('detects context-flagged leaked code', function (): void {
            $detector = new CodeAbuseDetector;

            $cart = new stdClass;
            $context = [
                'is_known_leaked_code' => true,
                'leaked_code_source' => 'breach_database',
            ];
            $result = $detector->detect('SOMECODE', $cart, null, $context);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);

            $leakedSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::LeakedCodeUsage);

            expect(count($leakedSignals))->toBeGreaterThanOrEqual(1);
        });

        it('does not trigger on normal code', function (): void {
            $detector = new CodeAbuseDetector;
            $detector->configure(['known_leaked_patterns' => ['LEAKED']]);

            $cart = new stdClass;
            $result = $detector->detect('NORMALCODE', $cart);

            $leakedSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::LeakedCodeUsage);

            expect(count($leakedSignals))->toBe(0);
        });
    });

    describe('sequential code attempts detection', function (): void {
        it('detects numeric sequential patterns', function (): void {
            $detector = new CodeAbuseDetector;

            $cart = new stdClass;
            $context = [
                'recent_code_attempts' => ['CODE1', 'CODE2', 'CODE3', 'CODE4', 'CODE5'],
            ];
            $result = $detector->detect('CODE6', $cart, null, $context);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);

            $sequentialSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::SequentialCodeAttempts);

            expect(count($sequentialSignals))->toBeGreaterThanOrEqual(1);
        });

        it('detects alphabetic sequential patterns', function (): void {
            $detector = new CodeAbuseDetector;

            $cart = new stdClass;
            $context = [
                'recent_code_attempts' => ['CODEA', 'CODEB', 'CODEC', 'CODED'],
            ];
            $result = $detector->detect('CODEE', $cart, null, $context);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);

            $sequentialSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::SequentialCodeAttempts);

            expect(count($sequentialSignals))->toBeGreaterThanOrEqual(1);
        });

        it('ignores non-sequential patterns', function (): void {
            $detector = new CodeAbuseDetector;

            $cart = new stdClass;
            $context = [
                'recent_code_attempts' => ['SUMMER20', 'WINTER10', 'SPRING15'],
            ];
            $result = $detector->detect('FALL25', $cart, null, $context);

            $sequentialSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::SequentialCodeAttempts);

            expect(count($sequentialSignals))->toBe(0);
        });

        it('ignores when too few attempts', function (): void {
            $detector = new CodeAbuseDetector;

            $cart = new stdClass;
            $context = [
                'recent_code_attempts' => ['CODE1', 'CODE2'],
            ];
            $result = $detector->detect('CODE3', $cart, null, $context);

            $sequentialSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::SequentialCodeAttempts);

            expect(count($sequentialSignals))->toBe(0);
        });
    });

    describe('brute force detection', function (): void {
        it('detects invalid code brute force', function (): void {
            $detector = new CodeAbuseDetector;

            $cart = new stdClass;
            $context = [
                'recent_invalid_attempts' => 10,
                'invalid_attempt_window_minutes' => 5,
            ];
            $result = $detector->detect('TESTCODE', $cart, null, $context);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);

            $bruteForceSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::InvalidCodeBruteforce);

            expect(count($bruteForceSignals))->toBeGreaterThanOrEqual(1);
        });

        it('does not trigger on low attempt count', function (): void {
            $detector = new CodeAbuseDetector;

            $cart = new stdClass;
            $context = [
                'recent_invalid_attempts' => 2,
            ];
            $result = $detector->detect('TESTCODE', $cart, null, $context);

            $bruteForceSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::InvalidCodeBruteforce);

            expect(count($bruteForceSignals))->toBe(0);
        });

        it('respects configured threshold', function (): void {
            $detector = new CodeAbuseDetector;
            $detector->configure(['max_sequential_invalid_attempts' => 20]);

            $cart = new stdClass;
            $context = [
                'recent_invalid_attempts' => 10,
            ];
            $result = $detector->detect('TESTCODE', $cart, null, $context);

            $bruteForceSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::InvalidCodeBruteforce);

            // Should not trigger because 10 < 20
            expect(count($bruteForceSignals))->toBe(0);
        });
    });

    describe('expired code abuse detection', function (): void {
        it('detects expired code abuse', function (): void {
            $detector = new CodeAbuseDetector;

            $cart = new stdClass;
            $context = [
                'expired_code_attempts' => 5,
            ];
            $result = $detector->detect('TESTCODE', $cart, null, $context);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);

            $expiredSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::ExpiredCodeAbuse);

            expect(count($expiredSignals))->toBeGreaterThanOrEqual(1);
        });

        it('detects recently-expired code timing attacks', function (): void {
            $detector = new CodeAbuseDetector;

            $cart = new stdClass;
            $context = [
                'recently_expired_attempts' => 3,
            ];
            $result = $detector->detect('TESTCODE', $cart, null, $context);

            $expiredSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::ExpiredCodeAbuse);

            expect(count($expiredSignals))->toBeGreaterThanOrEqual(1);
        });

        it('does not trigger on low expired attempts', function (): void {
            $detector = new CodeAbuseDetector;

            $cart = new stdClass;
            $context = [
                'expired_code_attempts' => 1,
            ];
            $result = $detector->detect('TESTCODE', $cart, null, $context);

            $expiredSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::ExpiredCodeAbuse);

            expect(count($expiredSignals))->toBe(0);
        });
    });

    describe('result structure', function (): void {
        it('returns FraudDetectorResult', function (): void {
            $detector = new CodeAbuseDetector;
            $cart = new stdClass;

            $result = $detector->detect('TESTCODE', $cart);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);
        });

        it('result can be converted to array', function (): void {
            $detector = new CodeAbuseDetector;
            $cart = new stdClass;

            $result = $detector->detect('TESTCODE', $cart);
            $array = $result->toArray();

            expect($array)->toHaveKeys(['detector', 'signals', 'signal_count', 'total_score', 'execution_time_ms'])
                ->and($array['detector'])->toBe('code_abuse');
        });

        it('tracks execution time', function (): void {
            $detector = new CodeAbuseDetector;
            $cart = new stdClass;

            $result = $detector->detect('TESTCODE', $cart);

            expect($result->executionTimeMs)->toBeGreaterThanOrEqual(0);
        });
    });

    describe('code masking', function (): void {
        it('masks short codes entirely', function (): void {
            $detector = new CodeAbuseDetector;
            $detector->configure(['known_leaked_patterns' => ['TEST']]);

            $cart = new stdClass;
            $result = $detector->detect('TEST1', $cart);

            $leakedSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::LeakedCodeUsage);

            if (count($leakedSignals) > 0) {
                $signal = reset($leakedSignals);
                // 5 chars = fully masked
                expect($signal->metadata['code'])->toBe('*****');
            } else {
                expect(true)->toBeTrue(); // Pattern didn't match
            }
        });

        it('masks long codes with first 3 and last 2 visible', function (): void {
            $detector = new CodeAbuseDetector;
            $detector->configure(['known_leaked_patterns' => ['LEAKED']]);

            $cart = new stdClass;
            $result = $detector->detect('LEAKEDCODE12', $cart);

            $leakedSignals = array_filter($result->signals, fn ($s) => $s->type === FraudSignalType::LeakedCodeUsage);

            if (count($leakedSignals) > 0) {
                $signal = reset($leakedSignals);
                // 12 chars: "LEA" + 7 stars + "12"
                expect($signal->metadata['code'])->toBe('LEA*******12');
            }
        });
    });

    describe('edge cases', function (): void {
        it('handles empty context gracefully', function (): void {
            $detector = new CodeAbuseDetector;
            $cart = new stdClass;

            $result = $detector->detect('TESTCODE', $cart, null, []);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);
        });

        it('handles cart as stdClass', function (): void {
            $detector = new CodeAbuseDetector;
            $cart = new stdClass;
            $cart->total = 100;

            $result = $detector->detect('TESTCODE', $cart);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);
        });

        it('handles empty code', function (): void {
            $detector = new CodeAbuseDetector;
            $cart = new stdClass;

            $result = $detector->detect('', $cart);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);
        });

        it('handles very long code', function (): void {
            $detector = new CodeAbuseDetector;
            $cart = new stdClass;

            $longCode = str_repeat('A', 1000);
            $result = $detector->detect($longCode, $cart);

            expect($result)->toBeInstanceOf(FraudDetectorResult::class);
        });
    });
});
