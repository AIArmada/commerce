<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\ResolveExperimentAssignment;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

function growthCreateOwner(): User
{
    return User::query()->create([
        'name' => 'Growth Test Owner ' . Str::random(6),
        'email' => 'growth-test-owner-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function growthCreateTrackedProperty(User $owner): TrackedProperty
{
    return OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Growth Resolve Property ' . Str::random(6),
        'slug' => 'growth-resolve-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));
}

function growthCreateExperiment(User $owner, ExperimentStatus $status = ExperimentStatus::Active): Experiment
{
    return OwnerContext::withOwner($owner, function () use ($owner, $status): Experiment {
        $trackedProperty = growthCreateTrackedProperty($owner);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'status' => $status,
        ]);

        Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'A',
            'name' => 'Control',
            'traffic_percentage' => 50,
            'position' => 1,
            'is_control' => true,
        ]);

        Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'B',
            'name' => 'Challenger',
            'traffic_percentage' => 50,
            'position' => 2,
            'is_control' => false,
        ]);

        return $experiment->fresh(['variants', 'trackedProperty']) ?? $experiment;
    });
}

function growthCreateIdentity(TrackedProperty $trackedProperty, User $owner): SignalIdentity
{
    return OwnerContext::withOwner($owner, fn (): SignalIdentity => SignalIdentity::query()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'external_id' => 'customer-' . Str::lower(Str::random(8)),
        'email' => 'customer-' . Str::lower(Str::random(8)) . '@example.com',
    ]));
}

function growthCreateSession(TrackedProperty $trackedProperty, User $owner, ?SignalIdentity $identity = null): SignalSession
{
    return OwnerContext::withOwner($owner, fn (): SignalSession => SignalSession::query()->create([
        'tracked_property_id' => $trackedProperty->getKey(),
        'signal_identity_id' => $identity?->getKey(),
        'session_identifier' => 'session-' . Str::lower(Str::random(8)),
        'started_at' => now(),
    ]));
}

it('returns the same assignment for the same identity and session', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $trackedProperty = $experiment->trackedProperty;
    $identity = growthCreateIdentity($trackedProperty, $owner);
    $session = growthCreateSession($trackedProperty, $owner, $identity);
    $action = app(ResolveExperimentAssignment::class);

    $first = OwnerContext::withOwner($owner, fn () => $action->handle($experiment, $identity, $session));
    $second = OwnerContext::withOwner($owner, fn () => $action->handle($experiment, $identity, $session));

    expect($second->getKey())->toBe($first->getKey())
        ->and($second->variant_id)->toBe($first->variant_id);
});

it('reuses a session assignment when the identity becomes known later', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $trackedProperty = $experiment->trackedProperty;
    $session = growthCreateSession($trackedProperty, $owner);
    $identity = growthCreateIdentity($trackedProperty, $owner);
    $action = app(ResolveExperimentAssignment::class);

    $sessionAssignment = OwnerContext::withOwner($owner, fn () => $action->handle($experiment, null, $session));
    $identityAssignment = OwnerContext::withOwner($owner, fn () => $action->handle($experiment, $identity, $session));

    expect($identityAssignment->getKey())->toBe($sessionAssignment->getKey())
        ->and($identityAssignment->signal_identity_id)->toBe((string) $identity->getKey());
});

it('rejects assignment resolution for non-active experiments', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner, ExperimentStatus::Draft);
    $identity = growthCreateIdentity($experiment->trackedProperty, $owner);
    $action = app(ResolveExperimentAssignment::class);

    expect(fn () => OwnerContext::withOwner($owner, fn () => $action->handle($experiment, $identity)))
        ->toThrow(InvalidArgumentException::class);
});

it('enforces unique assignment rows per experiment and signal session', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $variantA = $experiment->variants->firstWhere('code', 'A');
    $variantB = $experiment->variants->firstWhere('code', 'B');
    $session = growthCreateSession($experiment->trackedProperty, $owner);

    expect($variantA)->toBeInstanceOf(Variant::class)
        ->and($variantB)->toBeInstanceOf(Variant::class);

    OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variantA->getKey(),
        'signal_session_id' => $session->getKey(),
        'subject_key' => 'session:' . (string) $session->getKey(),
        'bucket' => 0,
        'assigned_at' => CarbonImmutable::now()->subSecond(),
        'first_exposed_at' => CarbonImmutable::now()->subSecond(),
        'last_seen_at' => CarbonImmutable::now()->subSecond(),
    ]));

    expect(fn (): Assignment => OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variantB->getKey(),
        'signal_session_id' => $session->getKey(),
        'subject_key' => 'identity:' . Str::lower(Str::random(8)),
        'bucket' => 1,
        'assigned_at' => CarbonImmutable::now(),
        'first_exposed_at' => CarbonImmutable::now(),
        'last_seen_at' => CarbonImmutable::now(),
    ])))->toThrow(QueryException::class);
});

it('enforces unique assignment rows per experiment and signal identity', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $variantA = $experiment->variants->firstWhere('code', 'A');
    $variantB = $experiment->variants->firstWhere('code', 'B');
    $identity = growthCreateIdentity($experiment->trackedProperty, $owner);

    expect($variantA)->toBeInstanceOf(Variant::class)
        ->and($variantB)->toBeInstanceOf(Variant::class);

    OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variantA->getKey(),
        'signal_identity_id' => $identity->getKey(),
        'subject_key' => 'identity:' . (string) $identity->getKey(),
        'bucket' => 0,
        'assigned_at' => CarbonImmutable::now()->subSecond(),
        'first_exposed_at' => CarbonImmutable::now()->subSecond(),
        'last_seen_at' => CarbonImmutable::now()->subSecond(),
    ]));

    expect(fn (): Assignment => OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variantB->getKey(),
        'signal_identity_id' => $identity->getKey(),
        'subject_key' => 'session:' . Str::lower(Str::random(8)),
        'bucket' => 1,
        'assigned_at' => CarbonImmutable::now(),
        'first_exposed_at' => CarbonImmutable::now(),
        'last_seen_at' => CarbonImmutable::now(),
    ])))->toThrow(QueryException::class);
});

it('consolidates matching session and identity assignments into the earliest assignment', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $variantA = $experiment->variants->firstWhere('code', 'A');
    $variantB = $experiment->variants->firstWhere('code', 'B');
    $identity = growthCreateIdentity($experiment->trackedProperty, $owner);
    $session = growthCreateSession($experiment->trackedProperty, $owner);
    $action = app(ResolveExperimentAssignment::class);

    expect($variantA)->toBeInstanceOf(Variant::class)
        ->and($variantB)->toBeInstanceOf(Variant::class);

    $sessionAssignment = OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variantA->getKey(),
        'signal_session_id' => $session->getKey(),
        'subject_key' => 'session:' . (string) $session->getKey(),
        'bucket' => 0,
        'assigned_at' => CarbonImmutable::now()->subSeconds(2),
        'first_exposed_at' => CarbonImmutable::now()->subSeconds(2),
        'last_seen_at' => CarbonImmutable::now()->subSeconds(2),
    ]));

    OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variantB->getKey(),
        'signal_identity_id' => $identity->getKey(),
        'subject_key' => 'identity:' . (string) $identity->getKey(),
        'bucket' => 1,
        'assigned_at' => CarbonImmutable::now()->subSecond(),
        'first_exposed_at' => CarbonImmutable::now()->subSecond(),
        'last_seen_at' => CarbonImmutable::now()->subSecond(),
    ]));

    $resolvedAssignment = OwnerContext::withOwner($owner, fn (): Assignment => $action->handle($experiment, $identity, $session));

    expect($resolvedAssignment->getKey())->toBe($sessionAssignment->getKey())
        ->and($resolvedAssignment->variant_id)->toBe($variantA->getKey())
        ->and($resolvedAssignment->signal_identity_id)->toBe((string) $identity->getKey())
        ->and($resolvedAssignment->signal_session_id)->toBe((string) $session->getKey())
        ->and(Assignment::query()->withoutOwnerScope()->where('experiment_id', $experiment->getKey())->count())->toBe(1);
});
