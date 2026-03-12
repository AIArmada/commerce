<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Cart\Facades\Cart;

beforeEach(function (): void {
    $this->affiliate = Affiliate::create([
        'code' => 'REPORT-1',
        'name' => 'Reporter',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'USD',
    ]);

    Cart::attachAffiliate($this->affiliate->code);
});

test('affiliate report service summarizes totals and utm', function (): void {
    $service = app(AffiliateService::class);

    $cart = app('cart')->getCurrentCart();

    $service->recordConversion($cart, [
        'order_reference' => 'RPT-1',
        'subtotal' => 1000,
        'metadata' => ['source' => 'newsletter', 'campaign' => 'spring'],
    ]);

    $service->recordConversion($cart, [
        'order_reference' => 'RPT-2',
        'subtotal' => 2000,
        'metadata' => ['source' => 'ads', 'campaign' => 'spring'],
    ]);

    $summary = app(AffiliateReportService::class)->affiliateSummary($this->affiliate->getKey());

    expect($summary['totals']['commission_minor'])->toBe(30)
        ->and($summary['totals']['conversions'])->toBe(2)
        ->and($summary['utm']['campaigns']['spring'])->toBe(2);
});

test('affiliate report service prefers neutral revenue fields and attribution first seen dates', function (): void {
    $occurredAt = now()->subDay()->startOfHour();

    AffiliateAttribution::create([
        'affiliate_id' => $this->affiliate->getKey(),
        'affiliate_code' => $this->affiliate->code,
        'subject_identifier' => 'event:ramadan-1',
        'subject_instance' => 'share',
        'first_seen_at' => $occurredAt,
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $this->affiliate->getKey(),
        'affiliate_code' => $this->affiliate->code,
        'subject_identifier' => 'event:ramadan-1',
        'subject_instance' => 'share',
        'external_reference' => 'REG-1001',
        'conversion_type' => 'registration',
        'subtotal_minor' => 0,
        'value_minor' => 4200,
        'total_minor' => 9900,
        'commission_minor' => 420,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => $occurredAt,
        'metadata' => ['source' => 'whatsapp', 'campaign' => 'ramadan'],
    ]);

    $service = app(AffiliateReportService::class);
    $startDate = $occurredAt->copy()->subHour();
    $endDate = $occurredAt->copy()->addHour();

    $summary = $service->getSummary($startDate, $endDate);
    $topAffiliates = $service->getTopAffiliates($startDate, $endDate);
    $trend = $service->getConversionTrend($startDate, $endDate);
    $affiliateSummary = $service->affiliateSummary($this->affiliate->getKey());

    expect($summary)->toMatchArray([
        'attributions' => 1,
        'conversions' => 1,
        'revenue_minor' => 4200,
        'commission_minor' => 420,
    ]);

    expect($topAffiliates[0])->toMatchArray([
        'affiliate_id' => $this->affiliate->getKey(),
        'affiliate_code' => $this->affiliate->code,
        'revenue_minor' => 4200,
        'commission_minor' => 420,
    ]);

    expect($trend[0])->toMatchArray([
        'conversions' => 1,
        'revenue_minor' => 4200,
        'commission_minor' => 420,
    ]);

    expect($affiliateSummary['totals'])->toMatchArray([
        'commission_minor' => 420,
        'revenue_minor' => 4200,
        'conversions' => 1,
        'ltv_minor' => 4200,
    ]);
});

test('affiliate report service returns top subjects with visits and conversions', function (): void {
    $occurredAt = now()->subHours(2)->startOfMinute();

    $eventAttribution = AffiliateAttribution::create([
        'affiliate_id' => $this->affiliate->getKey(),
        'affiliate_code' => $this->affiliate->code,
        'subject_type' => 'event',
        'subject_identifier' => 'event:ramadan-1',
        'subject_instance' => 'share',
        'subject_title_snapshot' => 'Ramadan Night',
        'first_seen_at' => $occurredAt,
    ]);

    AffiliateTouchpoint::create([
        'affiliate_attribution_id' => $eventAttribution->getKey(),
        'affiliate_id' => $this->affiliate->getKey(),
        'affiliate_code' => $this->affiliate->code,
        'subject_type' => 'event',
        'subject_identifier' => 'event:ramadan-1',
        'subject_instance' => 'share',
        'subject_title_snapshot' => 'Ramadan Night',
        'touched_at' => $occurredAt,
    ]);

    AffiliateTouchpoint::create([
        'affiliate_attribution_id' => $eventAttribution->getKey(),
        'affiliate_id' => $this->affiliate->getKey(),
        'affiliate_code' => $this->affiliate->code,
        'subject_type' => 'event',
        'subject_identifier' => 'event:ramadan-1',
        'subject_instance' => 'share',
        'subject_title_snapshot' => 'Ramadan Night',
        'touched_at' => $occurredAt->copy()->addMinute(),
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $this->affiliate->getKey(),
        'affiliate_code' => $this->affiliate->code,
        'affiliate_attribution_id' => $eventAttribution->getKey(),
        'subject_type' => 'event',
        'subject_identifier' => 'event:ramadan-1',
        'subject_instance' => 'share',
        'subject_title_snapshot' => 'Ramadan Night',
        'external_reference' => 'REG-2001',
        'conversion_type' => 'registration',
        'subtotal_minor' => 0,
        'value_minor' => 4200,
        'total_minor' => 4200,
        'commission_minor' => 420,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => $occurredAt,
    ]);

    $speakerAttribution = AffiliateAttribution::create([
        'affiliate_id' => $this->affiliate->getKey(),
        'affiliate_code' => $this->affiliate->code,
        'subject_type' => 'speaker',
        'subject_identifier' => 'speaker:ustaz-1',
        'subject_instance' => 'share',
        'subject_title_snapshot' => 'Ustaz Example',
        'first_seen_at' => $occurredAt,
    ]);

    AffiliateTouchpoint::create([
        'affiliate_attribution_id' => $speakerAttribution->getKey(),
        'affiliate_id' => $this->affiliate->getKey(),
        'affiliate_code' => $this->affiliate->code,
        'subject_type' => 'speaker',
        'subject_identifier' => 'speaker:ustaz-1',
        'subject_instance' => 'share',
        'subject_title_snapshot' => 'Ustaz Example',
        'touched_at' => $occurredAt,
    ]);

    $subjects = app(AffiliateReportService::class)->getTopSubjects(
        $occurredAt->copy()->subHour(),
        $occurredAt->copy()->addHour(),
    );

    expect($subjects[0])->toMatchArray([
        'subject_type' => 'event',
        'subject_identifier' => 'event:ramadan-1',
        'subject_title_snapshot' => 'Ramadan Night',
        'visits' => 2,
        'attributions' => 1,
        'conversions' => 1,
        'revenue_minor' => 4200,
        'commission_minor' => 420,
    ]);

    expect($subjects[1])->toMatchArray([
        'subject_type' => 'speaker',
        'subject_identifier' => 'speaker:ustaz-1',
        'subject_title_snapshot' => 'Ustaz Example',
        'visits' => 1,
        'attributions' => 1,
        'conversions' => 0,
        'revenue_minor' => 0,
        'commission_minor' => 0,
    ]);
});
