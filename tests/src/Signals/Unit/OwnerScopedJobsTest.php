<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Jobs\EvaluateSignalAlertsForEvent;
use AIArmada\Signals\Jobs\ReverseGeocodeSessionJob;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;

uses(SignalsTestCase::class);

it('fails hard when alert evaluation job receives a mismatched owner payload', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Signals Job Owner A',
        'email' => 'signals-job-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Signals Job Owner B',
        'email' => 'signals-job-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $property = TrackedProperty::query()->create([
        'name' => 'Signals Job Property',
        'slug' => 'signals-job-property',
        'write_key' => 'signals-job-property-key',
    ]);

    $event = SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'occurred_at' => now(),
        'event_name' => 'order.paid',
        'event_category' => 'conversion',
    ]);

    $job = new EvaluateSignalAlertsForEvent(
        signalEventId: $event->id,
        ownerType: $ownerB->getMorphClass(),
        ownerId: (string) $ownerB->getKey(),
    );

    expect(fn () => $job->handle())
        ->toThrow(RuntimeException::class, 'Signal event owner context mismatch.');
});

it('fails hard when reverse geocode job receives a mismatched owner payload', function (): void {
    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Signals Session Owner A',
        'email' => 'signals-session-owner-a@signals.test',
        'password' => 'secret',
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Signals Session Owner B',
        'email' => 'signals-session-owner-b@signals.test',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $property = TrackedProperty::query()->create([
        'name' => 'Signals Session Property',
        'slug' => 'signals-session-property',
        'write_key' => 'signals-session-property-key',
    ]);

    $session = SignalSession::query()->create([
        'tracked_property_id' => $property->id,
        'started_at' => now(),
        'latitude' => 3.14,
        'longitude' => 101.68,
    ]);

    $job = new ReverseGeocodeSessionJob(
        sessionId: $session->id,
        ownerType: $ownerB->getMorphClass(),
        ownerId: (string) $ownerB->getKey(),
    );

    expect(fn () => $job->handle())
        ->toThrow(RuntimeException::class, 'Signal session owner context mismatch.');
});