<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\ApproveAffiliate;
use AIArmada\Affiliates\Actions\Affiliates\CreateAffiliate;
use AIArmada\Affiliates\Actions\Affiliates\RejectAffiliate;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Services\DailyAggregationService;
use AIArmada\Affiliates\Services\ProgramService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Pending;
use Illuminate\Support\Facades\Event;

// Affiliate registration action tests
test('CreateAffiliate creates an affiliate', function (): void {
    $affiliate = app(CreateAffiliate::class)->handle([
        'code' => 'REG001',
        'name' => 'Registered Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    expect($affiliate)->toBeInstanceOf(Affiliate::class);
    expect($affiliate->code)->toBe('REG001');
});

test('ApproveAffiliate activates an affiliate', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'APPROVE001',
        'name' => 'Pending Affiliate',
        'status' => Pending::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $result = app(ApproveAffiliate::class)->handle($affiliate);

    expect($result->status->equals(Active::class))->toBeTrue();
});

test('RejectAffiliate disables an affiliate', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'REJECT001',
        'name' => 'Pending Affiliate',
        'status' => Pending::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $result = app(RejectAffiliate::class)->handle($affiliate);

    expect($result->status->equals(Disabled::class))->toBeTrue();
});

// DailyAggregationService Tests
test('DailyAggregationService aggregateForAffiliate creates or updates daily stats', function (): void {
    $service = app(DailyAggregationService::class);

    $affiliate = Affiliate::create([
        'code' => 'AGG001',
        'name' => 'Aggregation Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    // Test aggregation without touchpoints (simpler test)
    $stats = $service->aggregateForAffiliate($affiliate, now());

    expect($stats)->toHaveCount(1);

    $stat = $stats->first();

    expect($stat)->toBeInstanceOf(AffiliateDailyStat::class);
    expect($stat->clicks)->toBe(0);
    expect($stat->currency)->toBe('USD');
});

test('DailyAggregationService aggregateForAffiliate prefers neutral conversion values', function (): void {
    $service = app(DailyAggregationService::class);

    $affiliate = Affiliate::create([
        'code' => 'AGG006',
        'name' => 'Neutral Revenue Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subject_key' => 'event:agg-1',
        'subject_instance' => 'share',
        'external_reference' => 'REG-AGG-1',
        'conversion_type' => 'registration',
        'value_minor' => 4200,
        'total_minor' => 9000,
        'commission_minor' => 420,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]);

    $stats = $service->aggregateForAffiliate($affiliate, now());

    expect($stats)->toHaveCount(1);

    $stat = $stats->first();

    expect($stat->conversions)->toBe(1)
        ->and($stat->revenue_cents)->toBe(4200)
        ->and($stat->commission_cents)->toBe(420)
        ->and($stat->currency)->toBe('USD');
});

test('DailyAggregationService aggregateForAffiliate writes one row per currency', function (): void {
    $service = app(DailyAggregationService::class);

    $affiliate = Affiliate::create([
        'code' => 'AGG007',
        'name' => 'Multi Currency Aggregation Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    foreach ([
        ['currency' => 'USD', 'value' => 10000, 'commission' => 1000, 'ref' => 'AGG-MC-USD'],
        ['currency' => 'MYR', 'value' => 47000, 'commission' => 4700, 'ref' => 'AGG-MC-MYR'],
    ] as $leg) {
        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'subject_key' => 'order:' . $leg['ref'],
            'subject_instance' => 'share',
            'external_reference' => $leg['ref'],
            'conversion_type' => 'purchase',
            'subtotal_minor' => $leg['value'],
            'value_minor' => $leg['value'],
            'commission_minor' => $leg['commission'],
            'commission_currency' => $leg['currency'],
            'status' => ApprovedConversion::class,
            'occurred_at' => now(),
        ]);
    }

    $stats = $service->aggregateForAffiliate($affiliate, now());
    $byCurrency = $stats->mapWithKeys(fn (AffiliateDailyStat $stat): array => [$stat->currency => $stat]);

    expect($stats)->toHaveCount(2)
        ->and($byCurrency['USD']->revenue_cents)->toBe(10000)
        ->and($byCurrency['MYR']->revenue_cents)->toBe(47000)
        ->and($byCurrency['MYR']->commission_cents)->toBe(4700);

    $period = $service->getAggregatedStats($affiliate, now()->subDay(), now()->addDay());

    expect($period['conversions'])->toBe(2)
        ->and($period['revenue_cents'])->toBeNull()
        ->and($period['converted'])->toBeFalse()
        ->and($period['by_currency'])->toHaveKeys(['USD', 'MYR']);
});

test('DailyAggregationService aggregate processes all affiliates', function (): void {
    $service = app(DailyAggregationService::class);

    Affiliate::create([
        'code' => 'AGG002',
        'name' => 'Aggregate Test 1',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    Affiliate::create([
        'code' => 'AGG003',
        'name' => 'Aggregate Test 2',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $count = $service->aggregate(now());

    expect($count)->toBeGreaterThanOrEqual(2);
});

test('DailyAggregationService getAggregatedStats returns aggregated data', function (): void {
    $service = app(DailyAggregationService::class);

    $affiliate = Affiliate::create([
        'code' => 'AGG004',
        'name' => 'Stats Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    AffiliateDailyStat::create([
        'affiliate_id' => $affiliate->id,
        'date' => now()->subDays(2)->toDateString(),
        'currency' => 'USD',
        'clicks' => 100,
        'unique_clicks' => 80,
        'attributions' => 50,
        'conversions' => 10,
        'revenue_cents' => 50000,
        'commission_cents' => 5000,
        'refunds' => 0,
        'refund_amount_cents' => 0,
        'conversion_rate' => 10.0,
        'epc_cents' => 50.0,
    ]);

    AffiliateDailyStat::create([
        'affiliate_id' => $affiliate->id,
        'date' => now()->subDay()->toDateString(),
        'currency' => 'USD',
        'clicks' => 150,
        'unique_clicks' => 120,
        'attributions' => 75,
        'conversions' => 15,
        'revenue_cents' => 75000,
        'commission_cents' => 7500,
        'refunds' => 1,
        'refund_amount_cents' => 2500,
        'conversion_rate' => 10.0,
        'epc_cents' => 50.0,
    ]);

    $stats = $service->getAggregatedStats($affiliate, now()->subDays(5), now());

    expect($stats['clicks'])->toBe(250);
    expect($stats['conversions'])->toBe(25);
    expect($stats['revenue_cents'])->toBe(125000);
    expect($stats['commission_cents'])->toBe(12500);
});

test('DailyAggregationService backfill processes date range', function (): void {
    $service = app(DailyAggregationService::class);

    Affiliate::create([
        'code' => 'AGG005',
        'name' => 'Backfill Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $count = $service->backfill(now()->subDays(2), now());

    // Should process 3 days worth of stats for at least 1 affiliate = 3+ records
    expect($count)->toBeGreaterThanOrEqual(3);
});

// ProgramService Tests
test('ProgramService joinProgram creates membership', function (): void {
    Event::fake([AffiliateProgramJoined::class]);

    $service = app(ProgramService::class);

    $affiliate = Affiliate::create([
        'code' => 'JOIN001',
        'name' => 'Join Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Join Test Program',
        'slug' => 'join-test-program',
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $membership = $service->joinProgram($affiliate, $program);

    expect($membership)->toBeInstanceOf(AffiliateProgramMembership::class);
    expect($membership->status)->toBe(MembershipStatus::Approved);

    Event::assertDispatched(AffiliateProgramJoined::class);
});

test('ProgramService joinProgram with approval required sets pending status', function (): void {
    Event::fake([AffiliateProgramJoined::class]);

    $service = app(ProgramService::class);

    $affiliate = Affiliate::create([
        'code' => 'JOIN002',
        'name' => 'Pending Join Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Approval Program',
        'slug' => 'approval-program',
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => true,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $membership = $service->joinProgram($affiliate, $program);

    expect($membership->status)->toBe(MembershipStatus::Pending);
    Event::assertNotDispatched(AffiliateProgramJoined::class);
});

test('ProgramService leaveProgram removes membership', function (): void {
    $service = app(ProgramService::class);

    $affiliate = Affiliate::create([
        'code' => 'LEAVE001',
        'name' => 'Leave Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Leave Test Program',
        'slug' => 'leave-test-program',
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $service->joinProgram($affiliate, $program);

    expect($service->isMember($affiliate, $program))->toBeTrue();

    $service->leaveProgram($affiliate, $program);

    expect($service->isMember($affiliate, $program))->toBeFalse();
});

test('ProgramService getMembership returns membership details', function (): void {
    $service = app(ProgramService::class);

    $affiliate = Affiliate::create([
        'code' => 'MEMBD001',
        'name' => 'Membership Details Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Membership Details Program',
        'slug' => 'membership-details-program',
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'cookie_lifetime_days' => 30,
    ]);

    $service->joinProgram($affiliate, $program);

    $membership = $service->getMembership($affiliate, $program);

    expect($membership)->toBeInstanceOf(AffiliateProgramMembership::class);
    expect($membership->affiliate_id)->toBe($affiliate->id);
    expect($membership->program_id)->toBe($program->id);
});
