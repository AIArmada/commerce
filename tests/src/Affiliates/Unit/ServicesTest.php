<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Features\Identity\Actions\CreateAffiliate;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycle;
use AIArmada\Affiliates\Features\Programs\Api\MembershipRecord;
use AIArmada\Affiliates\Features\Programs\Api\ProgramEnrollment;
use AIArmada\Affiliates\Features\Programs\Api\ProgramMembershipReader;
use AIArmada\Affiliates\Features\Reporting\Api\DailyStatRecord;
use AIArmada\Affiliates\Features\Reporting\Api\DailyStatsReader;
use AIArmada\Affiliates\Features\Reporting\Api\DailyStatsWriter;
use AIArmada\Affiliates\Features\Reporting\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\Pending;
use Carbon\CarbonImmutable;
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

test('affiliate lifecycle approves a pending affiliate', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'APPROVE001',
        'name' => 'Pending Affiliate',
        'status' => Pending::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    expect(app(AffiliateLifecycle::class)->approve($affiliate->id))->toBeTrue()
        ->and(app(AffiliateLifecycle::class)->approve($affiliate->id))->toBeFalse()
        ->and($affiliate->refresh()->status->equals(Active::class))->toBeTrue();
});

test('affiliate lifecycle rejects a pending affiliate', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'REJECT001',
        'name' => 'Pending Affiliate',
        'status' => Pending::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    expect(app(AffiliateLifecycle::class)->reject($affiliate->id))->toBeTrue()
        ->and(app(AffiliateLifecycle::class)->reject($affiliate->id))->toBeFalse()
        ->and($affiliate->refresh()->status->equals(Disabled::class))->toBeTrue();
});

// DailyStatsWriter Tests
test('DailyStatsWriter aggregateForAffiliate creates or updates daily stats', function (): void {
    $service = app(DailyStatsWriter::class);

    $affiliate = Affiliate::create([
        'code' => 'AGG001',
        'name' => 'Aggregation Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    // Test aggregation without touchpoints (simpler test)
    $stats = $service->aggregateForAffiliate((string) $affiliate->getKey(), CarbonImmutable::now());

    expect($stats)->toHaveCount(1);

    $stat = $stats[0];

    expect($stat)->toBeInstanceOf(DailyStatRecord::class);
    expect($stat->clicks)->toBe(0);
    expect($stat->currency)->toBe('USD');
});

test('DailyStatsWriter aggregateForAffiliate prefers neutral conversion values', function (): void {
    $service = app(DailyStatsWriter::class);

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
        'occurred_at' => CarbonImmutable::now(),
    ]);

    $stats = $service->aggregateForAffiliate((string) $affiliate->getKey(), CarbonImmutable::now());

    expect($stats)->toHaveCount(1);

    $stat = $stats[0];

    expect($stat->conversions)->toBe(1)
        ->and($stat->revenueMinor)->toBe(4200)
        ->and($stat->commissionMinor)->toBe(420)
        ->and($stat->currency)->toBe('USD');
});

test('DailyStatsWriter aggregateForAffiliate writes one row per currency', function (): void {
    $service = app(DailyStatsWriter::class);

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
            'occurred_at' => CarbonImmutable::now(),
        ]);
    }

    $stats = $service->aggregateForAffiliate((string) $affiliate->getKey(), CarbonImmutable::now());
    $byCurrency = collect($stats)->keyBy('currency');

    expect($stats)->toHaveCount(2)
        ->and($byCurrency['USD']->revenueMinor)->toBe(10000)
        ->and($byCurrency['MYR']->revenueMinor)->toBe(47000)
        ->and($byCurrency['MYR']->commissionMinor)->toBe(4700);

    $period = app(DailyStatsReader::class)->getAggregatedStats((string) $affiliate->getKey(), CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay());

    expect($period['conversions'])->toBe(2)
        ->and($period['revenue_cents'])->toBeNull()
        ->and($period['converted'])->toBeFalse()
        ->and($period['by_currency'])->toHaveKeys(['USD', 'MYR']);
});

test('daily aggregation removes stale currency rows after source conversions change', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AGG-STALE', 'name' => 'Recalculation', 'status' => Active::class,
        'commission_type' => 'percentage', 'commission_rate' => 1000, 'currency' => 'USD',
    ]);
    $date = CarbonImmutable::now();
    AffiliateDailyStat::create([
        'affiliate_id' => $affiliate->id, 'date' => $date->toDateString(), 'currency' => 'EUR',
        'clicks' => 0, 'unique_clicks' => 0, 'attributions' => 0, 'conversions' => 1,
        'revenue_cents' => 500, 'commission_cents' => 50, 'refunds' => 0, 'refund_amount_cents' => 0,
        'conversion_rate' => 0, 'epc_cents' => 0,
    ]);

    $records = app(DailyStatsWriter::class)->aggregateForAffiliate((string) $affiliate->getKey(), $date);

    expect($records)->toHaveCount(1)
        ->and($records[0]->currency)->toBe('USD')
        ->and(AffiliateDailyStat::query()->where('affiliate_id', $affiliate->id)->where('currency', 'EUR')->exists())->toBeFalse();
});

test('DailyStatsWriter aggregate processes all affiliates', function (): void {
    $service = app(DailyStatsWriter::class);

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

    $count = $service->aggregate(CarbonImmutable::now());

    expect($count)->toBeGreaterThanOrEqual(2);
});

test('DailyStatsWriter getAggregatedStats returns aggregated data', function (): void {
    $service = app(DailyStatsWriter::class);

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
        'date' => CarbonImmutable::now()->subDays(2)->toDateString(),
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
        'date' => CarbonImmutable::now()->subDay()->toDateString(),
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

    $stats = app(DailyStatsReader::class)->getAggregatedStats((string) $affiliate->getKey(), CarbonImmutable::now()->subDays(5), CarbonImmutable::now());

    expect($stats['clicks'])->toBe(250);
    expect($stats['conversions'])->toBe(25);
    expect($stats['revenue_cents'])->toBe(125000);
    expect($stats['commission_cents'])->toBe(12500);
});

test('DailyStatsWriter backfill processes date range', function (): void {
    $service = app(DailyStatsWriter::class);

    Affiliate::create([
        'code' => 'AGG005',
        'name' => 'Backfill Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $count = $service->aggregateRange(CarbonImmutable::now()->subDays(2), CarbonImmutable::now());

    // Should process 3 days worth of stats for at least 1 affiliate = 3+ records
    expect($count)->toBeGreaterThanOrEqual(3);
});

// ProgramService Tests
test('ProgramService joinProgram creates membership', function (): void {
    Event::fake([AffiliateProgramJoined::class]);

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

    $membership = app(ProgramEnrollment::class)->join($affiliate->id, $program->id);

    expect($membership)->toBeInstanceOf(MembershipRecord::class);
    expect($membership->status)->toBe(MembershipStatus::Approved->value);

    Event::assertDispatched(AffiliateProgramJoined::class, fn (AffiliateProgramJoined $event): bool => $event->reference->membershipId === $membership->id
        && $event->reference->affiliate->id === $affiliate->id
        && $event->reference->programId === $program->id);
});

test('ProgramService joinProgram with approval required sets pending status', function (): void {
    Event::fake([AffiliateProgramJoined::class]);

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

    $membership = app(ProgramEnrollment::class)->join($affiliate->id, $program->id);

    expect($membership->status)->toBe(MembershipStatus::Pending->value)
        ->and($membership->isPending())->toBeTrue();
    Event::assertNotDispatched(AffiliateProgramJoined::class);
});

test('ProgramService leaveProgram removes membership', function (): void {
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

    app(ProgramEnrollment::class)->join($affiliate->id, $program->id);

    expect((app(ProgramMembershipReader::class)->find($affiliate->id, $program->id)?->active ?? false))->toBeTrue();

    app(ProgramEnrollment::class)->leave($affiliate->id, $program->id);

    expect((app(ProgramMembershipReader::class)->find($affiliate->id, $program->id)?->active ?? false))->toBeFalse();
});

test('ProgramService getMembership returns membership details', function (): void {
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

    app(ProgramEnrollment::class)->join($affiliate->id, $program->id);

    $membership = app(ProgramMembershipReader::class)->find($affiliate->id, $program->id);

    expect($membership)->toBeInstanceOf(MembershipRecord::class);
    expect($membership->affiliateId)->toBe($affiliate->id);
    expect($membership->programId)->toBe($program->id);
});
