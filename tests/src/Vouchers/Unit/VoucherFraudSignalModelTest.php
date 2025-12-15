<?php

declare(strict_types=1);

use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Fraud\Enums\FraudRiskLevel;
use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Fraud\Models\VoucherFraudSignal;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTestVoucher(string $code = 'TEST-CODE'): Voucher
{
    return Voucher::create([
        'code' => $code,
        'name' => 'Test Voucher',
        'type' => VoucherType::Percentage,
        'value' => 1000,
    ]);
}

function createFraudSignal(array $attributes = []): VoucherFraudSignal
{
    $voucher = createTestVoucher($attributes['voucher_code'] ?? 'FRAUD-TEST-' . uniqid());

    return VoucherFraudSignal::create(array_merge([
        'voucher_id' => $voucher->id,
        'voucher_code' => $voucher->code,
        'signal_type' => FraudSignalType::HighRedemptionVelocity,
        'score' => 75.0,
        'risk_level' => FraudRiskLevel::High,
        'message' => 'Test fraud signal',
        'detector' => 'velocity_detector',
        'metadata' => ['test' => 'data'],
        'context' => ['context' => 'value'],
        'user_id' => 'user-123',
        'ip_address' => '192.168.1.1',
        'was_blocked' => false,
        'reviewed' => false,
    ], $attributes));
}

describe('VoucherFraudSignal Model', function (): void {
    it('creates fraud signal with correct attributes', function (): void {
        $signal = createFraudSignal();

        expect($signal)->toBeInstanceOf(VoucherFraudSignal::class)
            ->and($signal->signal_type)->toBe(FraudSignalType::HighRedemptionVelocity)
            ->and($signal->score)->toBe(75.0)
            ->and($signal->risk_level)->toBe(FraudRiskLevel::High)
            ->and($signal->message)->toBe('Test fraud signal')
            ->and($signal->detector)->toBe('velocity_detector')
            ->and($signal->user_id)->toBe('user-123')
            ->and($signal->ip_address)->toBe('192.168.1.1')
            ->and($signal->was_blocked)->toBeFalse()
            ->and($signal->reviewed)->toBeFalse();
    });

    it('casts attributes correctly', function (): void {
        $signal = createFraudSignal([
            'metadata' => ['key' => 'value'],
            'context' => ['ctx' => 'data'],
        ]);

        expect($signal->metadata)->toBeArray()
            ->and($signal->context)->toBeArray()
            ->and($signal->was_blocked)->toBeBool()
            ->and($signal->reviewed)->toBeBool()
            ->and($signal->signal_type)->toBeInstanceOf(FraudSignalType::class)
            ->and($signal->risk_level)->toBeInstanceOf(FraudRiskLevel::class);
    });

    it('belongs to voucher', function (): void {
        $voucher = createTestVoucher('RELATION-TEST');
        $signal = VoucherFraudSignal::create([
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'signal_type' => FraudSignalType::UnusualTimePattern,
            'score' => 50.0,
            'risk_level' => FraudRiskLevel::Medium,
            'message' => 'Test message',
            'detector' => 'pattern_detector',
        ]);

        expect($signal->voucher)->toBeInstanceOf(Voucher::class)
            ->and($signal->voucher->id)->toBe($voucher->id);
    });

    it('marks signal as reviewed', function (): void {
        $signal = createFraudSignal();

        expect($signal->reviewed)->toBeFalse();

        $signal->markReviewed('reviewer-123', 'False positive');

        expect($signal->reviewed)->toBeTrue()
            ->and($signal->reviewed_by)->toBe('reviewer-123')
            ->and($signal->review_notes)->toBe('False positive')
            ->and($signal->reviewed_at)->not->toBeNull();
    });

    it('gets summary description', function (): void {
        $signal = createFraudSignal([
            'signal_type' => FraudSignalType::HighRedemptionVelocity,
            'risk_level' => FraudRiskLevel::High,
            'score' => 85.0,
            'message' => 'High velocity detected',
        ]);

        $summary = $signal->getSummary();

        expect($summary)->toContain('85')
            ->and($summary)->toContain('High velocity detected');
    });

    it('checks if review is required', function (): void {
        $highRiskSignal = createFraudSignal([
            'risk_level' => FraudRiskLevel::High,
            'reviewed' => false,
        ]);

        $lowRiskSignal = createFraudSignal([
            'risk_level' => FraudRiskLevel::Low,
            'reviewed' => false,
            'voucher_code' => 'LOW-RISK-' . uniqid(),
        ]);

        $reviewedSignal = createFraudSignal([
            'risk_level' => FraudRiskLevel::High,
            'reviewed' => true,
            'voucher_code' => 'REVIEWED-' . uniqid(),
        ]);

        expect($highRiskSignal->requiresReview())->toBeTrue()
            ->and($lowRiskSignal->requiresReview())->toBeFalse()
            ->and($reviewedSignal->requiresReview())->toBeFalse();
    });

    it('uses correct table name from config', function (): void {
        $signal = new VoucherFraudSignal;
        $table = $signal->getTable();

        expect($table)->toBe('voucher_fraud_signals');
    });
});

describe('VoucherFraudSignal Scopes', function (): void {
    beforeEach(function (): void {
        // Create signals with different states for scope testing
        VoucherFraudSignal::create([
            'voucher_code' => 'SCOPE-TEST-1',
            'signal_type' => FraudSignalType::HighRedemptionVelocity,
            'score' => 90.0,
            'risk_level' => FraudRiskLevel::Critical,
            'message' => 'Critical signal',
            'detector' => 'velocity_detector',
            'user_id' => 'user-a',
            'ip_address' => '10.0.0.1',
            'was_blocked' => true,
            'reviewed' => false,
        ]);

        VoucherFraudSignal::create([
            'voucher_code' => 'SCOPE-TEST-2',
            'signal_type' => FraudSignalType::UnusualTimePattern,
            'score' => 60.0,
            'risk_level' => FraudRiskLevel::High,
            'message' => 'High signal',
            'detector' => 'pattern_detector',
            'user_id' => 'user-b',
            'ip_address' => '10.0.0.2',
            'was_blocked' => false,
            'reviewed' => true,
        ]);

        VoucherFraudSignal::create([
            'voucher_code' => 'SCOPE-TEST-3',
            'signal_type' => FraudSignalType::CodeSharingDetected,
            'score' => 30.0,
            'risk_level' => FraudRiskLevel::Low,
            'message' => 'Low signal',
            'detector' => 'code_abuse_detector',
            'user_id' => 'user-a',
            'ip_address' => '10.0.0.1',
            'was_blocked' => false,
            'reviewed' => false,
        ]);
    });

    it('filters unreviewed signals', function (): void {
        $unreviewed = VoucherFraudSignal::unreviewed()->get();

        expect($unreviewed)->toHaveCount(2)
            ->and($unreviewed->pluck('reviewed')->unique()->first())->toBeFalse();
    });

    it('filters blocked signals', function (): void {
        $blocked = VoucherFraudSignal::blocked()->get();

        expect($blocked)->toHaveCount(1)
            ->and($blocked->first()->was_blocked)->toBeTrue();
    });

    it('filters by risk level', function (): void {
        $critical = VoucherFraudSignal::byRiskLevel(FraudRiskLevel::Critical)->get();
        $high = VoucherFraudSignal::byRiskLevel(FraudRiskLevel::High)->get();
        $low = VoucherFraudSignal::byRiskLevel(FraudRiskLevel::Low)->get();

        expect($critical)->toHaveCount(1)
            ->and($high)->toHaveCount(1)
            ->and($low)->toHaveCount(1);
    });

    it('filters high risk signals', function (): void {
        $highRisk = VoucherFraudSignal::highRisk()->get();

        expect($highRisk)->toHaveCount(2);
        $highRisk->each(function ($signal): void {
            expect($signal->risk_level->value)->toBeIn(['high', 'critical']);
        });
    });

    it('filters by detector', function (): void {
        $velocity = VoucherFraudSignal::byDetector('velocity_detector')->get();
        $pattern = VoucherFraudSignal::byDetector('pattern_detector')->get();

        expect($velocity)->toHaveCount(1)
            ->and($pattern)->toHaveCount(1);
    });

    it('filters by signal type', function (): void {
        $velocity = VoucherFraudSignal::bySignalType(FraudSignalType::HighRedemptionVelocity)->get();
        $pattern = VoucherFraudSignal::bySignalType(FraudSignalType::UnusualTimePattern)->get();
        $codeAbuse = VoucherFraudSignal::bySignalType(FraudSignalType::CodeSharingDetected)->get();

        expect($velocity)->toHaveCount(1)
            ->and($pattern)->toHaveCount(1)
            ->and($codeAbuse)->toHaveCount(1);
    });

    it('filters by user', function (): void {
        $userA = VoucherFraudSignal::forUser('user-a')->get();
        $userB = VoucherFraudSignal::forUser('user-b')->get();

        expect($userA)->toHaveCount(2)
            ->and($userB)->toHaveCount(1);
    });

    it('filters by IP address', function (): void {
        $ip1 = VoucherFraudSignal::fromIp('10.0.0.1')->get();
        $ip2 = VoucherFraudSignal::fromIp('10.0.0.2')->get();

        expect($ip1)->toHaveCount(2)
            ->and($ip2)->toHaveCount(1);
    });

    it('chains multiple scopes', function (): void {
        $result = VoucherFraudSignal::unreviewed()
            ->highRisk()
            ->get();

        expect($result)->toHaveCount(1)
            ->and($result->first()->risk_level)->toBe(FraudRiskLevel::Critical);
    });
});
