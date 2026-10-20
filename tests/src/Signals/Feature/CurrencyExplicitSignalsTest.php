<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Actions\IngestSignalEvent;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalDailyMetric;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalGoal;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Services\AcquisitionReportService;
use AIArmada\Signals\Services\ContentPerformanceReportService;
use AIArmada\Signals\Services\ConversionFunnelReportService;
use AIArmada\Signals\Services\GoalsReportService;
use AIArmada\Signals\Services\LiveActivityReportService;
use AIArmada\Signals\Services\SignalAlertEvaluator;
use AIArmada\Signals\Services\SignalMetricsAggregator;
use AIArmada\Signals\Services\SignalsDashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(SignalsTestCase::class);

function sig02_property(string $slug, string $currency = 'MYR'): TrackedProperty
{
    return TrackedProperty::query()->create([
        'name' => 'Currency ' . $slug,
        'slug' => 'sig02-' . $slug,
        'write_key' => 'sig02-' . $slug,
        'currency' => $currency,
    ]);
}

function sig02_metric(TrackedProperty $property, string $date, int $revenueMinor): SignalDailyMetric
{
    return SignalDailyMetric::query()->create([
        'tracked_property_id' => $property->id,
        'date' => $date,
        'unique_identities' => 1,
        'sessions' => 1,
        'bounced_sessions' => 0,
        'page_views' => 0,
        'events' => 1,
        'conversions' => 1,
        'revenue_minor' => $revenueMinor,
        'currency' => $property->currency,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function sig02_raw_event(TrackedProperty $property, array $overrides = []): void
{
    DB::table((new SignalEvent)->getTable())->insert(array_merge([
        'id' => (string) Str::uuid(),
        'tracked_property_id' => $property->id,
        'signal_session_id' => null,
        'signal_identity_id' => null,
        'owner_type' => $property->owner_type,
        'owner_id' => $property->owner_id,
        'occurred_at' => '2026-03-10 12:00:00',
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'ingestion_source' => 'trusted',
        'idempotency_key' => null,
        'source_event_id' => null,
        'path' => null,
        'url' => null,
        'referrer' => null,
        'source' => null,
        'medium' => null,
        'campaign' => null,
        'content' => null,
        'term' => null,
        'revenue_minor' => 0,
        'currency' => 'MYR',
        'properties' => null,
        'property_types' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('rejects trusted foreign-currency revenue before any rows are written', function (): void {
    $property = sig02_property('foreign-reject');

    expect(fn () => app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'external_id' => 'currency-foreign-customer',
        'session_identifier' => 'currency-foreign-session',
        'revenue_minor' => 10000,
        'currency' => 'USD',
    ], trusted: true))->toThrow(InvalidArgumentException::class);

    expect(SignalEvent::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0)
        ->and(SignalIdentity::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0)
        ->and(SignalSession::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->count())->toBe(0);
});

it('adopts the property currency for zero-value foreign and missing currencies', function (): void {
    $property = sig02_property('normalize');
    $ingest = app(IngestSignalEvent::class);

    $zeroForeign = $ingest->handle($property, [
        'event_name' => 'order.refunded',
        'event_category' => 'conversion',
        'revenue_minor' => 0,
        'currency' => 'usd',
    ], trusted: true);

    $missingCurrency = $ingest->handle($property, [
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'revenue_minor' => 7500,
    ], trusted: true);

    $matching = $ingest->handle($property, [
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'revenue_minor' => 2500,
        'currency' => 'myr',
    ], trusted: true);

    expect($zeroForeign->currency)->toBe('MYR')
        ->and($zeroForeign->revenue_minor)->toBe(0)
        ->and($missingCurrency->currency)->toBe('MYR')
        ->and($missingCurrency->revenue_minor)->toBe(7500)
        ->and($matching->currency)->toBe('MYR');
});

it('guards direct event writes and normalizes zero-revenue foreign currency', function (): void {
    $property = sig02_property('direct-guard');

    expect(fn () => SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => CarbonImmutable::parse('2026-10-17 10:00:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'revenue_minor' => 500,
        'currency' => 'USD',
    ]))->toThrow(InvalidArgumentException::class);

    $zero = SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => CarbonImmutable::parse('2026-10-17 11:00:00'),
        'event_name' => 'order.refunded',
        'event_category' => 'conversion',
        'revenue_minor' => 0,
        'currency' => 'USD',
    ]);

    $missing = SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => CarbonImmutable::parse('2026-10-17 12:00:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'revenue_minor' => 900,
    ]);

    expect($zero->currency)->toBe('MYR')
        ->and($missing->currency)->toBe('MYR');
});

it('normalizes property currency on create and rejects later changes', function (): void {
    $property = sig02_property('immutable', 'myr');

    expect($property->fresh()->currency)->toBe('MYR');

    expect(fn () => $property->update(['currency' => 'USD']))
        ->toThrow(InvalidArgumentException::class);

    expect($property->fresh()->currency)->toBe('MYR');

    $defaulted = TrackedProperty::query()->create([
        'name' => 'Currency Default',
        'slug' => 'sig02-default-' . Str::lower(Str::random(6)),
    ]);

    expect($defaulted->fresh()->currency)->toBe('MYR');
});

it('stamps daily metrics with the property currency and rejects direct mismatches', function (): void {
    $property = sig02_property('metric-currency');

    app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'occurred_at' => '2026-10-17 12:00:00',
        'revenue_minor' => 4000,
    ], trusted: true);

    $metric = app(SignalMetricsAggregator::class)->aggregateForDate(Carbon::parse('2026-10-17'), $property);

    expect($metric->currency)->toBe('MYR')
        ->and($metric->revenue_minor)->toBe(4000);

    expect(fn () => SignalDailyMetric::query()->create([
        'tracked_property_id' => $property->id,
        'date' => '2026-10-18',
        'revenue_minor' => 100,
        'currency' => 'USD',
    ]))->toThrow(InvalidArgumentException::class);

    $adopted = SignalDailyMetric::query()->create([
        'tracked_property_id' => $property->id,
        'date' => '2026-10-18',
        'revenue_minor' => 0,
    ]);

    expect($adopted->currency)->toBe('MYR');
});

it('keeps single and batch rollups equivalent and ignores legacy foreign-currency rows', function (): void {
    $property = sig02_property('rollup');
    $ingest = app(IngestSignalEvent::class);

    $ingest->handle($property, [
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'occurred_at' => '2026-10-17 10:00:00',
        'revenue_minor' => 10000,
    ], trusted: true);

    $ingest->handle($property, [
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'occurred_at' => '2026-10-17 11:00:00',
        'revenue_minor' => 5000,
    ], trusted: true);

    sig02_raw_event($property, [
        'occurred_at' => '2026-10-17 12:00:00',
        'currency' => 'USD',
        'revenue_minor' => 20000,
    ]);

    $aggregator = app(SignalMetricsAggregator::class);
    $single = $aggregator->aggregateForDate(Carbon::parse('2026-10-17'), $property);

    expect($single->revenue_minor)->toBe(15000)
        ->and($single->currency)->toBe('MYR')
        ->and($single->events)->toBe(3);

    SignalDailyMetric::query()->where('tracked_property_id', $property->id)->delete();
    $aggregator->backfill(Carbon::parse('2026-10-17'), Carbon::parse('2026-10-17'));

    $batch = SignalDailyMetric::query()
        ->where('tracked_property_id', $property->id)
        ->where('date', '2026-10-17')
        ->firstOrFail();

    expect($batch->revenue_minor)->toBe(15000)
        ->and($batch->currency)->toBe('MYR')
        ->and($batch->events)->toBe(3);
});

it('groups dashboard revenue by currency and returns scalar revenue with currency for a single property', function (): void {
    $myr = sig02_property('dash-myr', 'MYR');
    $usd = sig02_property('dash-usd', 'USD');

    sig02_metric($myr, '2026-10-17', 1000);
    sig02_metric($usd, '2026-10-17', 2000);

    $service = app(SignalsDashboardService::class);
    $start = CarbonImmutable::parse('2026-10-17 00:00:00');
    $end = CarbonImmutable::parse('2026-10-17 23:59:59');

    $summary = $service->summary(null, $start, $end);

    expect($summary['revenue'])->toBe(['MYR' => 1000, 'USD' => 2000])
        ->and($summary)->not->toHaveKey('revenue_minor');

    $single = $service->summary($myr, $start, $end);

    expect($single['revenue_minor'])->toBe(1000)
        ->and($single['currency'])->toBe('MYR')
        ->and($single)->not->toHaveKey('revenue');

    $trend = $service->trend(null, $start, $end);

    expect($trend[0]['revenue'])->toBe(['MYR' => 1000, 'USD' => 2000])
        ->and($trend[0])->not->toHaveKey('revenue_minor');

    $singleTrend = $service->trend($myr, $start, $end);

    expect($singleTrend[0]['revenue_minor'])->toBe(1000)
        ->and($singleTrend[0]['currency'])->toBe('MYR');
});

it('keeps currency aggregation isolated per owner', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->firstOrFail();

    $propertyA = sig02_property('tenant-a', 'MYR');
    sig02_metric($propertyA, '2026-10-17', 1000);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Currency Owner B',
        'email' => 'sig02-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = sig02_property('tenant-b', 'USD');
    sig02_metric($propertyB, '2026-10-17', 2000);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $summary = app(SignalsDashboardService::class)->summary(
        null,
        CarbonImmutable::parse('2026-10-17 00:00:00'),
        CarbonImmutable::parse('2026-10-17 23:59:59'),
    );

    expect($summary['revenue'])->toBe(['MYR' => 1000]);
});

it('keeps content performance revenue currency explicit in summaries and rows', function (): void {
    $myr = sig02_property('content-myr', 'MYR');
    $usd = sig02_property('content-usd', 'USD');

    SignalEvent::query()->create([
        'tracked_property_id' => $myr->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'path' => '/a',
        'revenue_minor' => 1000,
        'currency' => 'MYR',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $usd->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:05:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'path' => '/b',
        'revenue_minor' => 2000,
        'currency' => 'USD',
    ]);

    $service = app(ContentPerformanceReportService::class);

    $multi = $service->summary(null, '2026-03-10', '2026-03-10');

    expect($multi['revenue'])->toBe(['MYR' => 1000, 'USD' => 2000]);

    $single = $service->summary($myr->id, '2026-03-10', '2026-03-10');

    expect($single['revenue_minor'])->toBe(1000)
        ->and($single['currency'])->toBe('MYR');

    $byPath = collect($service->rows(null, '2026-03-10', '2026-03-10'))->keyBy('content_path');

    expect($byPath['/a']['revenue_minor'])->toBe(1000)
        ->and($byPath['/a']['currency'])->toBe('MYR')
        ->and($byPath['/b']['revenue_minor'])->toBe(2000)
        ->and($byPath['/b']['currency'])->toBe('USD');
});

it('groups acquisition and live activity revenue by currency scope', function (): void {
    $myr = sig02_property('acq-myr', 'MYR');
    $usd = sig02_property('acq-usd', 'USD');

    SignalEvent::query()->create([
        'tracked_property_id' => $myr->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'source' => 'google',
        'revenue_minor' => 1000,
        'currency' => 'MYR',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $usd->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:05:00'),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'source' => 'newsletter',
        'revenue_minor' => 2000,
        'currency' => 'USD',
    ]);

    $acquisition = app(AcquisitionReportService::class);

    expect($acquisition->summary(null, '2026-03-10', '2026-03-10')['revenue'])->toBe(['MYR' => 1000, 'USD' => 2000]);

    $single = $acquisition->summary($myr->id, '2026-03-10', '2026-03-10');

    expect($single['revenue_minor'])->toBe(1000)
        ->and($single['currency'])->toBe('MYR');

    $live = app(LiveActivityReportService::class);

    expect($live->summary(null, '2026-03-10', '2026-03-10')['revenue'])->toBe(['MYR' => 1000, 'USD' => 2000])
        ->and($live->summary($usd->id, '2026-03-10', '2026-03-10')['revenue_minor'])->toBe(2000);
});

it('returns scalar revenue with currency for property goals and a currency map for global goals', function (): void {
    $myr = sig02_property('goal-myr', 'MYR');
    $usd = sig02_property('goal-usd', 'USD');

    SignalGoal::query()->create([
        'tracked_property_id' => $myr->id,
        'name' => 'Bound goal',
        'slug' => 'sig02-bound-goal',
        'goal_type' => 'event',
        'event_name' => 'purchase.completed',
        'is_active' => true,
    ]);

    SignalGoal::query()->create([
        'name' => 'Global goal',
        'slug' => 'sig02-global-goal',
        'goal_type' => 'event',
        'event_name' => 'global.completed',
        'is_active' => true,
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $myr->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
        'event_name' => 'purchase.completed',
        'event_category' => 'conversion',
        'revenue_minor' => 1000,
        'currency' => 'MYR',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $myr->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:05:00'),
        'event_name' => 'global.completed',
        'event_category' => 'conversion',
        'revenue_minor' => 400,
        'currency' => 'MYR',
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $usd->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:10:00'),
        'event_name' => 'global.completed',
        'event_category' => 'conversion',
        'revenue_minor' => 600,
        'currency' => 'USD',
    ]);

    $service = app(GoalsReportService::class);
    $rows = collect($service->rows(null, '2026-03-10', '2026-03-10'))->keyBy('name');

    expect($rows['Bound goal']['revenue_minor'])->toBe(1000)
        ->and($rows['Bound goal']['currency'])->toBe('MYR')
        ->and($rows['Global goal']['revenue'])->toBe(['MYR' => 400, 'USD' => 600]);

    expect($service->summary(null, '2026-03-10', '2026-03-10')['revenue'])->toBe(['MYR' => 1400, 'USD' => 600]);

    $scoped = $service->summary($myr->id, '2026-03-10', '2026-03-10');

    expect($scoped['revenue_minor'])->toBe(1000)
        ->and($scoped['currency'])->toBe('MYR');
});

it('keeps conversion funnel revenue currency explicit across stages', function (): void {
    config()->set('signals.defaults.starter_funnel', [
        ['label' => 'Visited', 'event_name' => 'page_view', 'event_category' => 'page_view'],
        ['label' => 'Paid', 'event_name' => 'order.paid', 'event_category' => 'conversion'],
    ]);

    $myr = sig02_property('funnel-myr', 'MYR');
    $usd = sig02_property('funnel-usd', 'USD');

    $identityMyr = SignalIdentity::query()->create([
        'tracked_property_id' => $myr->id,
        'external_id' => 'funnel-myr',
        'first_seen_at' => CarbonImmutable::parse('2026-03-10 08:00:00'),
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 08:00:00'),
    ]);

    $identityUsd = SignalIdentity::query()->create([
        'tracked_property_id' => $usd->id,
        'external_id' => 'funnel-usd',
        'first_seen_at' => CarbonImmutable::parse('2026-03-10 08:00:00'),
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 08:00:00'),
    ]);

    foreach ([[$myr, $identityMyr, 'MYR', 1000], [$usd, $identityUsd, 'USD', 2000]] as [$property, $identity, $currency, $revenue]) {
        SignalEvent::query()->create([
            'tracked_property_id' => $property->id,
            'signal_identity_id' => $identity->id,
            'occurred_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
            'event_name' => 'page_view',
            'event_category' => 'page_view',
            'currency' => $currency,
        ]);

        SignalEvent::query()->create([
            'tracked_property_id' => $property->id,
            'signal_identity_id' => $identity->id,
            'occurred_at' => CarbonImmutable::parse('2026-03-10 09:05:00'),
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
            'revenue_minor' => $revenue,
            'currency' => $currency,
        ]);
    }

    $service = app(ConversionFunnelReportService::class);

    $multi = $service->summary(null, '2026-03-10', '2026-03-10');

    expect($multi['revenue'])->toBe(['MYR' => 1000, 'USD' => 2000]);

    $multiStages = $service->stages(null, '2026-03-10', '2026-03-10');

    expect($multiStages[1]['revenue'])->toBe(['MYR' => 1000, 'USD' => 2000]);

    $single = $service->summary($myr->id, '2026-03-10', '2026-03-10');

    expect($single['revenue_minor'])->toBe(1000)
        ->and($single['currency'])->toBe('MYR');

    $singleStages = $service->stages($myr->id, '2026-03-10', '2026-03-10');

    expect($singleStages[1]['revenue_minor'])->toBe(1000)
        ->and($singleStages[1]['currency'])->toBe('MYR');
});

it('requires an explicit currency for revenue alert rules without a property', function (): void {
    expect(fn () => SignalAlertRule::query()->create([
        'name' => 'Ambiguous revenue',
        'slug' => 'sig02-ambiguous-revenue',
        'metric_key' => 'revenue_minor',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 15,
        'severity' => 'warning',
        'priority' => 0,
        'is_active' => true,
    ]))->toThrow(RuntimeException::class);

    $property = sig02_property('alert-bound');

    $bound = SignalAlertRule::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Bound revenue',
        'slug' => 'sig02-bound-revenue',
        'metric_key' => 'revenue_minor',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 15,
        'severity' => 'warning',
        'priority' => 0,
        'is_active' => true,
    ]);

    $global = SignalAlertRule::query()->create([
        'name' => 'Global USD revenue',
        'slug' => 'sig02-global-usd-revenue',
        'metric_key' => 'revenue_minor',
        'operator' => '>=',
        'threshold' => 1,
        'event_filters' => ['currency' => 'usd'],
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 15,
        'severity' => 'warning',
        'priority' => 0,
        'is_active' => true,
    ]);

    expect($bound->revenueCurrency())->toBe('MYR')
        ->and($global->revenueCurrency())->toBe('USD');
});

it('filters alert revenue evaluation by currency and treats missing event currency as non-matching', function (): void {
    $property = sig02_property('alert-currency');

    app(IngestSignalEvent::class)->handle($property, [
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
        'occurred_at' => CarbonImmutable::now()->toIso8601String(),
        'revenue_minor' => 5000,
        'currency' => 'MYR',
        'properties' => ['channel' => 'web'],
    ], trusted: true);

    sig02_raw_event($property, [
        'occurred_at' => CarbonImmutable::now()->toDateTimeString(),
        'currency' => 'USD',
        'revenue_minor' => 3000,
        'properties' => json_encode(['channel' => 'web'], JSON_THROW_ON_ERROR),
    ]);

    sig02_raw_event($property, [
        'occurred_at' => CarbonImmutable::now()->toDateTimeString(),
        'currency' => '',
        'revenue_minor' => 9000,
        'properties' => json_encode(['channel' => 'web'], JSON_THROW_ON_ERROR),
    ]);

    $propertyRule = SignalAlertRule::query()->create([
        'tracked_property_id' => $property->id,
        'name' => 'Property revenue',
        'slug' => 'sig02-property-revenue',
        'metric_key' => 'revenue_minor',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 15,
        'severity' => 'warning',
        'priority' => 0,
        'is_active' => true,
    ]);

    $usdStreamingRule = SignalAlertRule::query()->create([
        'name' => 'Global streaming USD revenue',
        'slug' => 'sig02-streaming-usd-revenue',
        'metric_key' => 'revenue_minor',
        'operator' => '>=',
        'threshold' => 1,
        'event_filters' => [
            'currency' => 'usd',
            'properties' => [['key' => 'channel', 'value' => 'web']],
        ],
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 15,
        'severity' => 'warning',
        'priority' => 0,
        'is_active' => true,
    ]);

    DB::table((new SignalAlertRule)->getTable())->insert([
        'id' => (string) Str::uuid(),
        'owner_type' => $property->owner_type,
        'owner_id' => $property->owner_id,
        'owner_scope' => 'global',
        'tracked_property_id' => null,
        'name' => 'Legacy ambiguous revenue',
        'slug' => 'sig02-legacy-ambiguous-revenue',
        'metric_key' => 'revenue_minor',
        'operator' => '>=',
        'threshold' => 1,
        'event_filters' => json_encode([], JSON_THROW_ON_ERROR),
        'timeframe_minutes' => 60,
        'cooldown_minutes' => 15,
        'severity' => 'warning',
        'priority' => 0,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $legacyRule = SignalAlertRule::query()->where('slug', 'sig02-legacy-ambiguous-revenue')->firstOrFail();
    $evaluator = app(SignalAlertEvaluator::class);

    expect($evaluator->evaluate($propertyRule)['metric_value'])->toBe(5000.0)
        ->and($evaluator->evaluate($usdStreamingRule)['metric_value'])->toBe(3000.0)
        ->and($evaluator->evaluate($legacyRule)['metric_value'])->toBe(0.0);
});

it('keeps browser revenue at zero in the property currency', function (): void {
    $property = sig02_property('browser-revenue');

    $this->postJson('/api/signals/collect/browser-event', [
        'write_key' => $property->write_key,
        'event_name' => 'custom.browser.revenue',
        'properties' => ['cart_total_minor' => 5000],
    ])->assertAccepted();

    $event = SignalEvent::query()->withoutOwnerScope()
        ->where('tracked_property_id', $property->id)
        ->firstOrFail();

    expect($event->revenue_minor)->toBe(0)
        ->and($event->currency)->toBe('MYR');
});
