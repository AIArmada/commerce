<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\SignalDailyMetric;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;

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
        'is_bounce' => false,
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
        'is_bounce' => false,
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
        'is_bounce' => true,
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
        public function resolve(): ?\Illuminate\Database\Eloquent\Model
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
