<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\PendingConversion;
use Carbon\CarbonImmutable;

describe('AffiliateFraudSignal Model', function (): void {
    beforeEach(function (): void {
        $this->affiliate = Affiliate::create([
            'code' => 'FRAUD' . uniqid(),
            'name' => 'Fraud Test Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);
    });

    test('belongs to conversion when set', function (): void {
        $conversion = AffiliateConversion::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => 'ORD-FRAUD-001',
            'value_minor' => 50000,
            'commission_minor' => 5000,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
            'occurred_at' => now(),
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'conversion_id' => $conversion->id,
            'rule_code' => 'SELF_REFERRAL',
            'risk_points' => 100,
            'severity' => FraudSeverity::High,
            'description' => 'Self-referral detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal->conversion)->toBeInstanceOf(AffiliateConversion::class);
        expect($signal->conversion->id)->toBe($conversion->id);
    });

    test('belongs to touchpoint when set', function (): void {
        $attribution = AffiliateAttribution::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'visitor_fingerprint' => 'touchpoint123',
            'first_click_at' => now(),
            'last_click_at' => now(),
        ]);

        $touchpoint = AffiliateTouchpoint::create([
            'affiliate_id' => $this->affiliate->id,
            'affiliate_attribution_id' => $attribution->id,
            'affiliate_code' => $this->affiliate->code,
            'visitor_fingerprint' => 'fingerprint123',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'channel' => 'web',
            'entry_url' => 'https://example.com',
            'occurred_at' => now(),
        ]);

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'touchpoint_id' => $touchpoint->id,
            'rule_code' => 'SUSPICIOUS_IP',
            'risk_points' => 30,
            'severity' => FraudSeverity::Low,
            'description' => 'Suspicious IP detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal->touchpoint)->toBeInstanceOf(AffiliateTouchpoint::class);
        expect($signal->touchpoint->id)->toBe($touchpoint->id);
    });

    test('scopeHighSeverity returns high and critical signals', function (): void {
        AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'LOW1',
            'risk_points' => 10,
            'severity' => FraudSeverity::Low,
            'description' => 'Low severity',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'HIGH1',
            'risk_points' => 80,
            'severity' => FraudSeverity::High,
            'description' => 'High severity',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'CRITICAL1',
            'risk_points' => 100,
            'severity' => FraudSeverity::Critical,
            'description' => 'Critical severity',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        $highSeverity = AffiliateFraudSignal::highSeverity()->get();

        expect($highSeverity)->toHaveCount(2);
        expect($highSeverity->pluck('rule_code')->toArray())->toContain('HIGH1');
        expect($highSeverity->pluck('rule_code')->toArray())->toContain('CRITICAL1');
    });

    test('can store evidence array', function (): void {
        $evidence = [
            'ip_address' => '192.168.1.100',
            'clicks_per_minute' => 150,
            'patterns' => ['rapid', 'automated'],
        ];

        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'EVIDENCE_TEST',
            'risk_points' => 75,
            'severity' => FraudSeverity::High,
            'description' => 'Test with evidence',
            'evidence' => $evidence,
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal->evidence)->toBeArray();
        expect($signal->evidence['ip_address'])->toBe('192.168.1.100');
        expect($signal->evidence['clicks_per_minute'])->toBe(150);
        expect($signal->evidence['patterns'])->toContain('rapid');
    });

    test('casts status correctly', function (): void {
        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'STATUS_CAST',
            'risk_points' => 50,
            'severity' => FraudSeverity::Medium,
            'description' => 'Test status cast',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);

        expect($signal->status)->toBeInstanceOf(FraudSignalStatus::class);
        expect($signal->status)->toBe(FraudSignalStatus::Detected);
    });

    test('casts detected_at to Carbon', function (): void {
        $signal = AffiliateFraudSignal::create([
            'affiliate_id' => $this->affiliate->id,
            'rule_code' => 'DATETIME_CAST',
            'risk_points' => 50,
            'severity' => FraudSeverity::Medium,
            'description' => 'Test datetime cast',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => '2024-06-15 10:30:00',
        ]);

        expect($signal->detected_at)->toBeInstanceOf(CarbonImmutable::class);
        expect($signal->detected_at->format('Y-m-d'))->toBe('2024-06-15');
    });
});
