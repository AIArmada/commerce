<?php

declare(strict_types=1);

use AIArmada\Affiliates\Events\FraudSignalDetected;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\Features\Fraud\Api\FraudApi;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSeverity;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalStatus;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\Support\IpHasher;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->affiliate = Affiliate::create([
        'code' => 'FRAUD-TEST',
        'name' => 'Fraud Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    // Create attribution for touchpoints
    $this->attribution = AffiliateAttribution::create([
        'affiliate_id' => $this->affiliate->id,
        'affiliate_code' => $this->affiliate->code,
        'first_seen_at' => now()->subHour(),
        'expires_at' => now()->addDays(30),
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Mozilla/5.0 Test',
    ]);

    $this->fraudService = app(FraudApi::class);

    // Enable fraud detection
    config([
        'affiliates.fraud.velocity.enabled' => true,
        'affiliates.fraud.velocity.max_clicks_per_hour' => 10,
        'affiliates.fraud.velocity.max_conversions_per_day' => 5,
        'affiliates.fraud.anomaly.geo.enabled' => true,
        'affiliates.fraud.anomaly.conversion_time.min_seconds' => 5,
        'affiliates.fraud.blocking_threshold' => 100,
        'affiliates.tracking.fingerprint.enabled' => true,
        'affiliates.tracking.fingerprint.threshold' => 3,
        'affiliates.tracking.block_self_referral' => true,
    ]);
});

test('click velocity fraud is detected when threshold is exceeded', function (): void {
    Event::fake([FraudSignalDetected::class]);

    // Get fresh service instance after event faking
    $fraudService = app(FraudApi::class);

    $request = Request::create('/landing', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.100');
    $request->headers->set('User-Agent', 'Mozilla/5.0 Test Browser');

    // Prefill the cache to simulate previous clicks
    $cacheKey = "fraud:clicks:{$this->affiliate->id}:" . IpHasher::hash('192.168.1.100');
    Cache::put($cacheKey, 10, now()->addHour());

    $result = $fraudService->analyzeClick((string) $this->affiliate->getKey(), $request->ip(), $request->userAgent());

    expect($result->signals)->not->toBeEmpty();
    expect($result->score)->toBeGreaterThan(0);

    $velocitySignal = collect($result->signals)->firstWhere('ruleCode', 'CLICK_VELOCITY');
    expect($velocitySignal)->not->toBeNull();
    expect($velocitySignal->riskPoints)->toBe(30);

    Event::assertDispatched(FraudSignalDetected::class);
});

test('geo anomaly fraud is detected for rapid IP changes', function (): void {
    Event::fake([FraudSignalDetected::class]);

    // Get fresh service instance after event faking
    $fraudService = app(FraudApi::class);

    // Create recent touchpoint with different IP (within 5 minute window)
    AffiliateTouchpoint::create([
        'affiliate_attribution_id' => $this->attribution->id,
        'affiliate_id' => $this->affiliate->id,
        'affiliate_code' => $this->affiliate->code,
        'ip_address' => IpHasher::hash('10.0.0.1'),
        'user_agent' => 'Mozilla/5.0',
        'touched_at' => now()->subMinutes(3),
    ]);

    $request = Request::create('/landing', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.100');
    $request->headers->set('User-Agent', 'Mozilla/5.0 Test Browser');

    $result = $fraudService->analyzeClick((string) $this->affiliate->getKey(), $request->ip(), $request->userAgent());

    $geoSignal = collect($result->signals)->firstWhere('ruleCode', 'GEO_ANOMALY');
    expect($geoSignal)->not->toBeNull();
    expect($geoSignal->severity)->toBe(FraudSeverity::High);
    expect($geoSignal->riskPoints)->toBe(40);

    Event::assertDispatched(FraudSignalDetected::class);
});

test('fingerprint repeat fraud is detected', function (): void {
    Event::fake([FraudSignalDetected::class]);

    // Get fresh service instance after event faking
    $fraudService = app(FraudApi::class);

    $fingerprint = hash('sha256', 'Mozilla/5.0 Test|192.168.1.1');

    // Create multiple touchpoints with same fingerprint
    for ($i = 1; $i <= 4; $i++) {
        AffiliateTouchpoint::create([
            'affiliate_attribution_id' => $this->attribution->id,
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 Test',
            'touched_at' => now()->subHours(rand(1, 12)),
            'fingerprint' => $fingerprint,
        ]);
    }

    $request = Request::create('/landing', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.1');
    $request->headers->set('User-Agent', 'Mozilla/5.0 Test');

    $result = $fraudService->analyzeClick((string) $this->affiliate->getKey(), $request->ip(), $request->userAgent());

    $fpSignal = collect($result->signals)->firstWhere('ruleCode', 'FINGERPRINT_REPEAT');
    expect($fpSignal)->not->toBeNull();
    expect($fpSignal->severity)->toBe(FraudSeverity::Medium);
});

test('conversion velocity fraud is detected when daily limit exceeded', function (): void {
    Event::fake([FraudSignalDetected::class]);

    // Get fresh service instance after event faking
    $fraudService = app(FraudApi::class);

    // Create conversions to hit the daily limit
    for ($i = 1; $i <= 5; $i++) {
        AffiliateConversion::create([
            'commission_currency' => 'USD',
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'external_reference' => "ORDER-{$i}",
            'subtotal_minor' => 5000,
            'value_minor' => 5000,
            'commission_minor' => 250,
            'status' => 'pending',
            'occurred_at' => today(),
        ]);
    }

    // Create the conversion that exceeds the limit
    $conversion = AffiliateConversion::create([
        'commission_currency' => 'USD',
        'affiliate_id' => $this->affiliate->id,
        'affiliate_code' => $this->affiliate->code,
        'external_reference' => 'ORDER-OVER-LIMIT',
        'subtotal_minor' => 5000,
        'value_minor' => 5000,
        'commission_minor' => 250,
        'status' => 'pending',
        'occurred_at' => today(),
    ]);

    $result = $fraudService->analyzeConversion((string) $conversion->getKey());

    $velocitySignal = collect($result->signals)->firstWhere('ruleCode', 'CONVERSION_VELOCITY');
    expect($velocitySignal)->not->toBeNull();
    expect($velocitySignal->riskPoints)->toBe(35);
});

test('self referral fraud is detected', function (): void {
    Event::fake([FraudSignalDetected::class]);

    // Get fresh service instance after event faking
    $fraudService = app(FraudApi::class);

    config(['auth.providers.users.model' => User::class]);

    // Affiliate owned by user-123, purchase acted by the same user
    $this->affiliate->forceFill([
        'owner_type' => (new User)->getMorphClass(),
        'owner_id' => 'user-123',
    ])->save();

    $conversion = AffiliateConversion::create([
        'commission_currency' => 'USD',
        'affiliate_id' => $this->affiliate->id,
        'affiliate_code' => $this->affiliate->code,
        'external_reference' => 'SELF-ORDER',
        'subtotal_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 500,
        'status' => 'pending',
        'occurred_at' => now(),
        'actor_user_id' => 'user-123',
    ]);

    $result = $fraudService->analyzeConversion((string) $conversion->getKey());

    $selfRefSignal = collect($result->signals)->firstWhere('ruleCode', 'SELF_REFERRAL');
    expect($selfRefSignal)->not->toBeNull();
    expect($selfRefSignal->severity)->toBe(FraudSeverity::Critical);
    expect($selfRefSignal->riskPoints)->toBe(100);
    expect($result->allowed)->toBeFalse();
});

test('fast conversion fraud is detected for suspiciously quick conversions', function (): void {
    Event::fake([FraudSignalDetected::class]);

    // Get fresh service instance after event faking
    $fraudService = app(FraudApi::class);

    // Enable conversion time fraud check
    config(['affiliates.fraud.anomaly.conversion_time.enabled' => true]);
    config(['affiliates.fraud.anomaly.conversion_time.min_seconds' => 5]);

    $clickTime = now()->subSeconds(2);

    // Create attribution that happened recently
    $attribution = AffiliateAttribution::create([
        'affiliate_id' => $this->affiliate->id,
        'affiliate_code' => $this->affiliate->code,
        'first_seen_at' => $clickTime,
        'expires_at' => now()->addDays(30),
    ]);

    // Create conversion just seconds after the click
    $conversion = AffiliateConversion::create([
        'commission_currency' => 'USD',
        'affiliate_id' => $this->affiliate->id,
        'affiliate_code' => $this->affiliate->code,
        'affiliate_attribution_id' => $attribution->id,
        'external_reference' => 'FAST-ORDER',
        'subtotal_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 500,
        'status' => 'pending',
        'occurred_at' => now(),
    ]);

    $result = $fraudService->analyzeConversion((string) $conversion->getKey());

    $fastSignal = collect($result->signals)->firstWhere('ruleCode', 'FAST_CONVERSION');
    expect($fastSignal)->not->toBeNull()
        ->and($fastSignal->severity)->toBe(FraudSeverity::High)
        ->and($fastSignal->riskPoints)->toBe(45);

    // At minimum, the result should be allowed since no critical signals
    expect($result->allowed)->toBeTrue();
});

test('risk profile aggregates signals correctly', function (): void {
    // Create various fraud signals
    AffiliateFraudSignal::create([
        'affiliate_id' => $this->affiliate->id,
        'rule_code' => 'CLICK_VELOCITY',
        'risk_points' => 30,
        'severity' => FraudSeverity::Medium,
        'description' => 'Velocity exceeded',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now()->subDays(5),
    ]);

    AffiliateFraudSignal::create([
        'affiliate_id' => $this->affiliate->id,
        'rule_code' => 'GEO_ANOMALY',
        'risk_points' => 40,
        'severity' => FraudSeverity::High,
        'description' => 'Geo anomaly detected',
        'status' => FraudSignalStatus::Confirmed,
        'detected_at' => now()->subDays(10),
    ]);

    AffiliateFraudSignal::create([
        'affiliate_id' => $this->affiliate->id,
        'rule_code' => 'FINGERPRINT_REPEAT',
        'risk_points' => 25,
        'severity' => FraudSeverity::Medium,
        'description' => 'Fingerprint repeated',
        'status' => FraudSignalStatus::Dismissed,
        'detected_at' => now()->subDays(15),
    ]);

    $profile = $this->fraudService->getRiskProfile((string) $this->affiliate->getKey());

    expect($profile['total_score'])->toBe(95);
    expect($profile['signal_count'])->toBe(3);
    expect($profile['pending_review'])->toBe(1);
    expect($profile['confirmed'])->toBe(1);
    expect($profile['by_rule'])->toHaveKeys(['CLICK_VELOCITY', 'GEO_ANOMALY', 'FINGERPRINT_REPEAT']);
});

test('clean click passes all fraud checks', function (): void {
    Event::fake([FraudSignalDetected::class]);

    // Get fresh service instance after event faking
    $fraudService = app(FraudApi::class);

    $request = Request::create('/landing', 'GET');
    $request->server->set('REMOTE_ADDR', '203.0.113.50');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

    $result = $fraudService->analyzeClick((string) $this->affiliate->getKey(), $request->ip(), $request->userAgent());

    expect($result->allowed)->toBeTrue();
    expect($result->score)->toBe(0);
    expect($result->signals)->toBeEmpty();

    Event::assertNotDispatched(FraudSignalDetected::class);
});

test('clean conversion passes all fraud checks', function (): void {
    Event::fake([FraudSignalDetected::class]);

    // Get fresh service instance after event faking
    $fraudService = app(FraudApi::class);

    // Create attribution from 10 minutes ago
    $attribution = AffiliateAttribution::create([
        'affiliate_id' => $this->affiliate->id,
        'affiliate_code' => $this->affiliate->code,
        'first_seen_at' => now()->subMinutes(10),
        'expires_at' => now()->addDays(30),
    ]);

    $conversion = AffiliateConversion::create([
        'commission_currency' => 'USD',
        'affiliate_id' => $this->affiliate->id,
        'affiliate_code' => $this->affiliate->code,
        'attribution_id' => $attribution->id,
        'external_reference' => 'CLEAN-ORDER',
        'subtotal_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 500,
        'status' => 'pending',
        'occurred_at' => now(),
    ]);

    $result = $fraudService->analyzeConversion((string) $conversion->getKey());

    expect($result->allowed)->toBeTrue();
    expect($result->score)->toBe(0);
    expect($result->signals)->toBeEmpty();

    Event::assertNotDispatched(FraudSignalDetected::class);
});

test('multiple fraud signals accumulate to block traffic', function (): void {
    Event::fake([FraudSignalDetected::class]);

    // Get fresh service instance after event faking
    $fraudService = app(FraudApi::class);

    $fingerprint = hash('sha256', 'Mozilla/5.0 Bot|192.168.1.1');

    // Create touchpoints for fingerprint fraud
    for ($i = 1; $i <= 4; $i++) {
        AffiliateTouchpoint::create([
            'affiliate_attribution_id' => $this->attribution->id,
            'affiliate_id' => $this->affiliate->id,
            'affiliate_code' => $this->affiliate->code,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 Bot',
            'touched_at' => now()->subHours(rand(1, 12)),
            'fingerprint' => $fingerprint,
        ]);
    }

    // Create recent touchpoint for geo anomaly
    AffiliateTouchpoint::create([
        'affiliate_attribution_id' => $this->attribution->id,
        'affiliate_id' => $this->affiliate->id,
        'affiliate_code' => $this->affiliate->code,
        'ip_address' => IpHasher::hash('10.0.0.1'),
        'user_agent' => 'Mozilla/5.0',
        'touched_at' => now()->subMinutes(1),
    ]);

    // Prefill velocity cache
    $cacheKey = "fraud:clicks:{$this->affiliate->id}:" . IpHasher::hash('192.168.1.1');
    Cache::put($cacheKey, 10, now()->addHour());

    $request = Request::create('/landing', 'GET');
    $request->server->set('REMOTE_ADDR', '192.168.1.1');
    $request->headers->set('User-Agent', 'Mozilla/5.0 Bot');

    $result = $fraudService->analyzeClick((string) $this->affiliate->getKey(), $request->ip(), $request->userAgent());

    // Should have multiple signals
    expect(count($result->signals))->toBeGreaterThanOrEqual(2);
    expect($result->score)->toBeGreaterThan(50);
});

test('fraud severity correctly maps from score', function (): void {
    expect(FraudSeverity::fromScore(10))->toBe(FraudSeverity::Low);
    expect(FraudSeverity::fromScore(50))->toBe(FraudSeverity::Medium);
    expect(FraudSeverity::fromScore(80))->toBe(FraudSeverity::High);
    expect(FraudSeverity::fromScore(100))->toBe(FraudSeverity::Critical);
});
