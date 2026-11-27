<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Signals\Features\Ingestion\Models\SignalEvent;
use AIArmada\Signals\Features\Ingestion\Models\SignalIdentity;
use AIArmada\Signals\Features\Ingestion\Models\SignalSession;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use AIArmada\Signals\Features\Reporting\Actions\AggregateDailyMetrics;
use AIArmada\Signals\Features\Reporting\Models\SignalDailyMetric;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

uses(SignalsTestCase::class);

it('aggregates daily metrics from raw records', function (): void {
    /** @var User $owner */
    $owner = User::query()->create([
        'name' => 'Aggregate Owner',
        'email' => 'aggregate-owner@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Aggregate Property',
        'slug' => 'aggregate-property',
        'write_key' => 'aggregate-write-key',
    ]);
    $property->assignOwner($owner)->save();

    $identity = SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'external_id' => 'identity-1',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 08:00:00'),
    ]);
    $identity->assignOwner($owner)->save();

    $session = SignalSession::query()->create([
        'tracked_property_id' => $property->id,
        'signal_identity_id' => $identity->id,
        'session_identifier' => 'session-1',
        'started_at' => CarbonImmutable::parse('2026-03-10 08:00:00'),
    ]);
    $session->assignOwner($owner)->save();

    $pageView = SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'signal_session_id' => $session->id,
        'signal_identity_id' => $identity->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 08:01:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
    ]);
    $pageView->assignOwner($owner)->save();

    $conversion = SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'signal_session_id' => $session->id,
        'signal_identity_id' => $identity->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 08:05:00'),
        'event_name' => 'purchase_completed',
        'event_category' => 'conversion',
        'revenue_minor' => 2500,
    ]);
    $conversion->assignOwner($owner)->save();

    $this->artisan('signals:aggregate-daily', ['--date' => '2026-03-10'])
        ->assertExitCode(0);

    $metric = SignalDailyMetric::query()->withoutOwnerScope()->where('tracked_property_id', $property->id)->where('date', '2026-03-10')->first();

    expect($metric)->not()->toBeNull()
        ->and($metric?->unique_identities)->toBe(1)
        ->and($metric?->sessions)->toBe(1)
        ->and($metric?->page_views)->toBe(1)
        ->and($metric?->events)->toBe(2)
        ->and($metric?->conversions)->toBe(1)
        ->and($metric?->revenue_minor)->toBe(2500);
});

it('aggregates daily metrics for each owner when no ambient owner is resolved', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Aggregate Multi Owner A',
        'email' => 'aggregate-multi-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Aggregate Multi Owner B',
        'email' => 'aggregate-multi-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $propertyA = TrackedProperty::query()->create([
        'name' => 'Aggregate Multi Property A',
        'slug' => 'aggregate-multi-property-a',
        'write_key' => 'aggregate-multi-property-a-key',
    ]);

    $identityA = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyA->id,
        'external_id' => 'aggregate-multi-identity-a',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 08:00:00'),
    ]);

    $sessionA = SignalSession::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_identity_id' => $identityA->id,
        'session_identifier' => 'aggregate-multi-session-a',
        'started_at' => CarbonImmutable::parse('2026-03-10 08:00:00'),
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'signal_session_id' => $sessionA->id,
        'signal_identity_id' => $identityA->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 08:01:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    $propertyB = TrackedProperty::query()->create([
        'name' => 'Aggregate Multi Property B',
        'slug' => 'aggregate-multi-property-b',
        'write_key' => 'aggregate-multi-property-b-key',
    ]);

    $identityB = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyB->id,
        'external_id' => 'aggregate-multi-identity-b',
        'last_seen_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
    ]);

    $sessionB = SignalSession::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_identity_id' => $identityB->id,
        'session_identifier' => 'aggregate-multi-session-b',
        'started_at' => CarbonImmutable::parse('2026-03-10 09:00:00'),
    ]);

    SignalEvent::query()->create([
        'tracked_property_id' => $propertyB->id,
        'signal_session_id' => $sessionB->id,
        'signal_identity_id' => $identityB->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-10 09:01:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
    ]);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?RecordReference
        {
            return null;
        }
    });

    $this->artisan('signals:aggregate-daily', ['--date' => '2026-03-10'])
        ->assertExitCode(0);

    $metrics = SignalDailyMetric::query()
        ->withoutOwnerScope()
        ->where('date', '2026-03-10')
        ->orderBy('tracked_property_id')
        ->get();

    expect($metrics)->toHaveCount(2)
        ->and($metrics->pluck('tracked_property_id')->all())->toContain($propertyA->id, $propertyB->id)
        ->and($metrics->firstWhere('tracked_property_id', $propertyA->id)?->sessions)->toBe(1)
        ->and($metrics->firstWhere('tracked_property_id', $propertyB->id)?->sessions)->toBe(1);
});

it('rejects daily aggregation of a property outside the current owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $property = TrackedProperty::query()->create([
        'name' => 'Private rollup property',
        'slug' => 'private-rollup-property',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

    expect(fn () => app(AggregateDailyMetrics::class)
        ->aggregateForDate(CarbonImmutable::parse('2026-03-10'), $property->id))
        ->toThrow(ModelNotFoundException::class);
});

it('keeps repeated daily rollups idempotent for the same property and date', function (): void {
    $property = TrackedProperty::query()->create([
        'name' => 'Idempotent rollup property',
        'slug' => 'idempotent-rollup-property',
        'write_key' => 'idempotent-rollup-key',
    ]);
    SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => CarbonImmutable::parse('2026-03-11 08:00:00'),
        'event_name' => 'page_view',
        'event_category' => 'page_view',
    ]);

    $first = app(AggregateDailyMetrics::class)->aggregateForDate(CarbonImmutable::parse('2026-03-11'), $property->id);
    $second = app(AggregateDailyMetrics::class)->aggregateForDate(CarbonImmutable::parse('2026-03-11'), $property->id);

    expect($second->id)->toBe($first->id)
        ->and($second->events)->toBe(1)
        ->and(SignalDailyMetric::query()->where('tracked_property_id', $property->id)->where('date', '2026-03-11')->count())->toBe(1);
});

it('persists a full property batch in one rollup write', function (): void {
    foreach (range(1, 100) as $number) {
        TrackedProperty::query()->create([
            'name' => "Rollup property {$number}",
            'slug' => "rollup-property-{$number}",
        ]);
    }

    $date = CarbonImmutable::parse('2026-03-11');
    DB::enableQueryLog();

    try {
        $count = app(AggregateDailyMetrics::class)->aggregateRange($date, $date);
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($count)->toBe(100)
        ->and(count($queries))->toBeLessThanOrEqual(6)
        ->and(SignalDailyMetric::query()->count())->toBe(100);
});
