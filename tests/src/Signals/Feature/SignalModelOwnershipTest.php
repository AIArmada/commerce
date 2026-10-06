<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Models\SignalAlertDelivery;
use AIArmada\Signals\Models\SignalAlertLog;
use AIArmada\Signals\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalDailyMetric;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalInteractionRule;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

uses(SignalsTestCase::class);

function ownershipGuardOwner(string $suffix): User
{
    return User::query()->create([
        'name' => 'Ownership Guard ' . $suffix,
        'email' => 'ownership-guard-' . $suffix . '@example.com',
        'password' => 'secret',
    ]);
}

function ownershipGuardProperty(User $owner, string $suffix): TrackedProperty
{
    return OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Ownership Guard ' . $suffix,
        'slug' => 'ownership-guard-' . $suffix . '-' . Str::lower(Str::random(6)),
        'type' => 'website',
        'currency' => 'MYR',
        'is_active' => true,
    ]));
}

it('rejects cross-owner tracked properties on child model writes', function (): void {
    $ownerA = ownershipGuardOwner('a');
    $ownerB = ownershipGuardOwner('b');
    $propertyB = ownershipGuardProperty($ownerB, 'b');

    OwnerContext::withOwner($ownerA, function () use ($propertyB): void {
        expect(fn (): SignalEvent => SignalEvent::query()->create([
            'tracked_property_id' => $propertyB->id,
            'event_name' => 'custom.cross-owner',
            'occurred_at' => CarbonImmutable::now(),
            'idempotency_key' => 'cross-owner-event',
        ]))->toThrow(AuthorizationException::class);

        expect(fn (): SignalIdentity => SignalIdentity::query()->create([
            'tracked_property_id' => $propertyB->id,
            'anonymous_id' => 'cross-owner-anon',
        ]))->toThrow(AuthorizationException::class);

        expect(fn (): SignalSession => SignalSession::query()->create([
            'tracked_property_id' => $propertyB->id,
            'session_identifier' => 'cross-owner-session',
            'started_at' => CarbonImmutable::now(),
        ]))->toThrow(AuthorizationException::class);

        expect(fn (): SignalDailyMetric => SignalDailyMetric::query()->create([
            'tracked_property_id' => $propertyB->id,
            'date' => '2026-10-01',
        ]))->toThrow(AuthorizationException::class);

        expect(fn (): SignalInteractionRule => SignalInteractionRule::query()->create([
            'tracked_property_id' => $propertyB->id,
            'name' => 'Cross Owner Rule',
            'slug' => 'cross-owner-rule-' . Str::lower(Str::random(6)),
            'trigger_type' => 'click',
            'event_name' => 'custom.cross-owner',
        ]))->toThrow(AuthorizationException::class);
    });

    expect(SignalEvent::query()->withoutOwnerScope()->count())->toBe(0);
});

it('rejects cross-owner alert logs on delivery writes', function (): void {
    $ownerA = ownershipGuardOwner('delivery-a');
    $ownerB = ownershipGuardOwner('delivery-b');
    $propertyB = ownershipGuardProperty($ownerB, 'delivery-b');

    $ruleB = OwnerContext::withOwner($ownerB, fn (): SignalAlertRule => SignalAlertRule::query()->create([
        'tracked_property_id' => $propertyB->id,
        'name' => 'Owner B Rule',
        'slug' => 'owner-b-rule-' . Str::lower(Str::random(6)),
        'metric_key' => 'events',
        'operator' => '>=',
        'threshold' => 1,
        'timeframe_minutes' => 5,
        'cooldown_minutes' => 1,
        'severity' => 'warning',
        'channels' => ['database'],
    ]));

    $logB = OwnerContext::withOwner($ownerB, fn (): SignalAlertLog => SignalAlertLog::query()->create([
        'signal_alert_rule_id' => $ruleB->id,
        'tracked_property_id' => $propertyB->id,
        'metric_key' => 'events',
        'operator' => '>=',
        'metric_value' => 2,
        'threshold_value' => 1,
        'severity' => 'warning',
        'title' => 'Owner B Alert',
    ]));

    OwnerContext::withOwner($ownerA, function () use ($logB): void {
        expect(fn (): SignalAlertDelivery => SignalAlertDelivery::query()->create([
            'signal_alert_log_id' => $logB->id,
            'channel' => 'webhook',
            'destination_key' => 'ops',
            'status' => 'pending',
        ]))->toThrow(AuthorizationException::class);
    });
});

it('rejects mismatched sessions and identities across properties of one owner', function (): void {
    $owner = ownershipGuardOwner('same-owner');
    $propertyOne = ownershipGuardProperty($owner, 'one');
    $propertyTwo = ownershipGuardProperty($owner, 'two');

    [$identityTwo, $sessionTwo] = OwnerContext::withOwner($owner, function () use ($propertyTwo): array {
        $identity = SignalIdentity::query()->create([
            'tracked_property_id' => $propertyTwo->id,
            'anonymous_id' => 'property-two-anon',
        ]);
        $session = SignalSession::query()->create([
            'tracked_property_id' => $propertyTwo->id,
            'signal_identity_id' => $identity->id,
            'session_identifier' => 'property-two-session',
            'started_at' => CarbonImmutable::now(),
        ]);

        return [$identity, $session];
    });

    OwnerContext::withOwner($owner, function () use ($propertyOne, $identityTwo, $sessionTwo): void {
        expect(fn (): SignalEvent => SignalEvent::query()->create([
            'tracked_property_id' => $propertyOne->id,
            'signal_session_id' => $sessionTwo->id,
            'event_name' => 'custom.mismatch',
            'occurred_at' => CarbonImmutable::now(),
        ]))->toThrow(AuthorizationException::class);

        expect(fn (): SignalEvent => SignalEvent::query()->create([
            'tracked_property_id' => $propertyOne->id,
            'signal_identity_id' => $identityTwo->id,
            'event_name' => 'custom.mismatch',
            'occurred_at' => CarbonImmutable::now(),
        ]))->toThrow(AuthorizationException::class);

        expect(fn (): SignalSession => SignalSession::query()->create([
            'tracked_property_id' => $propertyOne->id,
            'signal_identity_id' => $identityTwo->id,
            'session_identifier' => 'mismatched-session',
            'started_at' => CarbonImmutable::now(),
        ]))->toThrow(AuthorizationException::class);
    });
});

it('rejects reassigning child rows to a foreign property on update', function (): void {
    $ownerA = ownershipGuardOwner('update-a');
    $ownerB = ownershipGuardOwner('update-b');
    $propertyA = ownershipGuardProperty($ownerA, 'update-a');
    $propertyB = ownershipGuardProperty($ownerB, 'update-b');

    $event = OwnerContext::withOwner($ownerA, fn (): SignalEvent => SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'event_name' => 'custom.reassign',
        'occurred_at' => CarbonImmutable::now(),
    ]));

    OwnerContext::withOwner($ownerA, function () use ($event, $propertyB): void {
        expect(fn () => $event->forceFill(['tracked_property_id' => $propertyB->id])->save())
            ->toThrow(AuthorizationException::class);
    });

    expect($event->refresh()->tracked_property_id)->toBe($propertyA->id);
});

it('keeps reads isolated between owners', function (): void {
    $ownerA = ownershipGuardOwner('read-a');
    $ownerB = ownershipGuardOwner('read-b');
    $propertyA = ownershipGuardProperty($ownerA, 'read-a');

    OwnerContext::withOwner($ownerA, fn (): SignalEvent => SignalEvent::query()->create([
        'tracked_property_id' => $propertyA->id,
        'event_name' => 'custom.private',
        'occurred_at' => CarbonImmutable::now(),
    ]));

    $visibleToB = OwnerContext::withOwner($ownerB, fn (): int => SignalEvent::query()->count());

    expect($visibleToB)->toBe(0)
        ->and(SignalEvent::query()->withoutOwnerScope()->count())->toBe(1);
});

it('allows explicit global writes for global-only records', function (): void {
    $rule = OwnerContext::withOwner(null, fn (): SignalInteractionRule => SignalInteractionRule::query()->create([
        'tracked_property_id' => null,
        'name' => 'Global Rule',
        'slug' => 'global-rule-' . Str::lower(Str::random(6)),
        'trigger_type' => 'click',
        'event_name' => 'custom.global',
    ]));

    expect($rule->owner_type)->toBeNull()->and($rule->owner_id)->toBeNull();

    $property = OwnerContext::withOwner(null, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Global Property',
        'slug' => 'global-property-' . Str::lower(Str::random(6)),
        'type' => 'website',
        'currency' => 'MYR',
        'is_active' => true,
    ]));

    $identity = OwnerContext::withOwner(null, fn (): SignalIdentity => SignalIdentity::query()->create([
        'tracked_property_id' => $property->id,
        'anonymous_id' => 'global-anon',
    ]));

    expect($identity->owner_type)->toBeNull();
});

it('rejects owned properties from explicit global writes', function (): void {
    $owner = ownershipGuardOwner('global-owned');
    $property = ownershipGuardProperty($owner, 'global-owned');

    OwnerContext::withOwner(null, function () use ($property): void {
        expect(fn (): SignalEvent => SignalEvent::query()->create([
            'tracked_property_id' => $property->id,
            'event_name' => 'custom.global-owned',
            'occurred_at' => CarbonImmutable::now(),
        ]))->toThrow(AuthorizationException::class);
    });
});

it('permits standalone writes when owner scoping is disabled', function (): void {
    config()->set('signals.owner.enabled', false);

    $owner = ownershipGuardOwner('disabled');
    $property = TrackedProperty::query()->create([
        'name' => 'Standalone Property',
        'slug' => 'standalone-property-' . Str::lower(Str::random(6)),
        'type' => 'website',
        'currency' => 'MYR',
        'is_active' => true,
    ]);
    $property->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ])->save();

    $event = SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'event_name' => 'custom.standalone',
        'occurred_at' => CarbonImmutable::now(),
    ]);

    expect($event->exists)->toBeTrue();
});

it('prevents same-owner property reassignment when telemetry links already exist', function (): void {
    $owner = ownershipGuardOwner('reassign-same');
    $propertyOne = ownershipGuardProperty($owner, 'reassign-one');
    $propertyTwo = ownershipGuardProperty($owner, 'reassign-two');

    [$identity, $session, $event] = OwnerContext::withOwner($owner, function () use ($propertyOne): array {
        $identity = SignalIdentity::query()->create([
            'tracked_property_id' => $propertyOne->id,
            'anonymous_id' => 'reassign-anon',
        ]);
        $session = SignalSession::query()->create([
            'tracked_property_id' => $propertyOne->id,
            'signal_identity_id' => $identity->id,
            'session_identifier' => 'reassign-session',
            'started_at' => CarbonImmutable::now(),
        ]);
        $event = SignalEvent::query()->create([
            'tracked_property_id' => $propertyOne->id,
            'signal_session_id' => $session->id,
            'signal_identity_id' => $identity->id,
            'event_name' => 'custom.reassign-same',
            'occurred_at' => CarbonImmutable::now(),
        ]);

        return [$identity, $session, $event];
    });

    OwnerContext::withOwner($owner, function () use ($identity, $session, $event, $propertyTwo): void {
        expect(fn () => $identity->forceFill(['tracked_property_id' => $propertyTwo->id])->save())
            ->toThrow(AuthorizationException::class, 'immutable');

        expect(fn () => $session->forceFill(['tracked_property_id' => $propertyTwo->id])->save())
            ->toThrow(AuthorizationException::class, 'immutable');

        expect(fn () => $event->forceFill(['tracked_property_id' => $propertyTwo->id])->save())
            ->toThrow(AuthorizationException::class, 'immutable');
    });

    expect($identity->refresh()->tracked_property_id)->toBe($propertyOne->id)
        ->and($session->refresh()->tracked_property_id)->toBe($propertyOne->id)
        ->and($event->refresh()->tracked_property_id)->toBe($propertyOne->id);
});

it('rejects dangling references when owner scoping is disabled', function (): void {
    config()->set('signals.owner.enabled', false);

    $property = TrackedProperty::query()->create([
        'name' => 'Disabled Dangling Property',
        'slug' => 'disabled-dangling-' . Str::lower(Str::random(6)),
        'type' => 'website',
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    $missingPropertyId = (string) Str::uuid();
    $missingSessionId = (string) Str::uuid();
    $missingIdentityId = (string) Str::uuid();

    expect(fn (): SignalEvent => SignalEvent::query()->create([
        'tracked_property_id' => $missingPropertyId,
        'event_name' => 'custom.dangling-property',
        'occurred_at' => CarbonImmutable::now(),
    ]))->toThrow(AuthorizationException::class, 'tracked_property_id');

    expect(fn (): SignalIdentity => SignalIdentity::query()->create([
        'tracked_property_id' => $missingPropertyId,
        'anonymous_id' => 'dangling-anon',
    ]))->toThrow(AuthorizationException::class, 'tracked_property_id');

    expect(fn (): SignalSession => SignalSession::query()->create([
        'tracked_property_id' => $missingPropertyId,
        'session_identifier' => 'dangling-session',
        'started_at' => CarbonImmutable::now(),
    ]))->toThrow(AuthorizationException::class, 'tracked_property_id');

    expect(fn (): SignalEvent => SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'signal_session_id' => $missingSessionId,
        'event_name' => 'custom.dangling-session',
        'occurred_at' => CarbonImmutable::now(),
    ]))->toThrow(AuthorizationException::class, 'signal_session_id');

    expect(fn (): SignalEvent => SignalEvent::query()->create([
        'tracked_property_id' => $property->id,
        'signal_identity_id' => $missingIdentityId,
        'event_name' => 'custom.dangling-identity',
        'occurred_at' => CarbonImmutable::now(),
    ]))->toThrow(AuthorizationException::class, 'signal_identity_id');

    expect(fn (): SignalSession => SignalSession::query()->create([
        'tracked_property_id' => $property->id,
        'signal_identity_id' => $missingIdentityId,
        'session_identifier' => 'dangling-identity-session',
        'started_at' => CarbonImmutable::now(),
    ]))->toThrow(AuthorizationException::class, 'signal_identity_id');
});

it('still rejects same-property mismatches when owner scoping is disabled', function (): void {
    config()->set('signals.owner.enabled', false);

    $propertyOne = TrackedProperty::query()->create([
        'name' => 'Disabled Mismatch One',
        'slug' => 'disabled-mismatch-one-' . Str::lower(Str::random(6)),
        'type' => 'website',
        'currency' => 'MYR',
        'is_active' => true,
    ]);
    $propertyTwo = TrackedProperty::query()->create([
        'name' => 'Disabled Mismatch Two',
        'slug' => 'disabled-mismatch-two-' . Str::lower(Str::random(6)),
        'type' => 'website',
        'currency' => 'MYR',
        'is_active' => true,
    ]);

    $identityTwo = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyTwo->id,
        'anonymous_id' => 'disabled-mismatch-anon',
    ]);
    $sessionTwo = SignalSession::query()->create([
        'tracked_property_id' => $propertyTwo->id,
        'session_identifier' => 'disabled-mismatch-session',
        'started_at' => CarbonImmutable::now(),
    ]);

    expect(fn (): SignalEvent => SignalEvent::query()->create([
        'tracked_property_id' => $propertyOne->id,
        'signal_identity_id' => $identityTwo->id,
        'event_name' => 'custom.disabled-mismatch',
        'occurred_at' => CarbonImmutable::now(),
    ]))->toThrow(AuthorizationException::class);

    expect(fn (): SignalEvent => SignalEvent::query()->create([
        'tracked_property_id' => $propertyOne->id,
        'signal_session_id' => $sessionTwo->id,
        'event_name' => 'custom.disabled-mismatch',
        'occurred_at' => CarbonImmutable::now(),
    ]))->toThrow(AuthorizationException::class);

    $identity = SignalIdentity::query()->create([
        'tracked_property_id' => $propertyOne->id,
        'anonymous_id' => 'disabled-immutable-anon',
    ]);

    expect(fn () => $identity->forceFill(['tracked_property_id' => $propertyTwo->id])->save())
        ->toThrow(AuthorizationException::class, 'immutable');
});
