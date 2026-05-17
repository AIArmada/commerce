<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\BuildExperimentSignalProperties;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function growthSignalPropertiesOwner(): User
{
    return User::query()->create([
        'name' => 'Growth Signal Properties Owner ' . Str::random(6),
        'email' => 'growth-signal-properties-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function growthSignalPropertiesExperiment(User $owner, string $suffix): array
{
    return OwnerContext::withOwner($owner, function () use ($suffix): array {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Signal Context Property ' . $suffix,
            'slug' => 'signal-context-' . Str::slug($suffix) . '-' . Str::lower(Str::random(6)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'name' => 'Signal Context Experiment ' . $suffix,
            'slug' => 'signal-context-experiment-' . Str::slug($suffix) . '-' . Str::lower(Str::random(6)),
            'status' => 'active',
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => Str::upper($suffix),
            'name' => 'Signal Context Variant ' . $suffix,
            'traffic_percentage' => 100,
            'position' => 1,
            'is_control' => true,
        ]);

        return [$trackedProperty, $experiment, $variant];
    });
}

it('rejects assignment context resolution when the stored experiment belongs to another owner tuple', function (): void {
    $ownerA = growthSignalPropertiesOwner();
    $ownerB = growthSignalPropertiesOwner();

    [, $experimentA, $variantA] = growthSignalPropertiesExperiment($ownerA, 'owner-a');
    [, $experimentB] = growthSignalPropertiesExperiment($ownerB, 'owner-b');

    $assignmentId = (string) Str::uuid();
    $timestamp = CarbonImmutable::now();

    DB::table((new Assignment)->getTable())->insert([
        'id' => $assignmentId,
        'experiment_id' => $experimentB->getKey(),
        'variant_id' => $variantA->getKey(),
        'signal_identity_id' => null,
        'signal_session_id' => null,
        'subject_key' => 'anonymous:corrupt-owner-tuple',
        'bucket' => 0,
        'metadata' => json_encode([]),
        'assigned_at' => $timestamp,
        'first_exposed_at' => $timestamp,
        'last_seen_at' => $timestamp,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => (string) $ownerA->getKey(),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $assignment = OwnerContext::withOwner($ownerA, fn (): Assignment => Assignment::query()->findOrFail($assignmentId));

    expect(fn (): array => OwnerContext::withOwner($ownerA, fn (): array => app(BuildExperimentSignalProperties::class)->handle($assignment)))
        ->toThrow(InvalidArgumentException::class, 'Assignment experiment context could not be resolved.');
});

it('rejects assignment context resolution when the assignment itself is outside the current owner scope', function (): void {
    $ownerA = growthSignalPropertiesOwner();
    $ownerB = growthSignalPropertiesOwner();

    [, $experiment, $variant] = growthSignalPropertiesExperiment($ownerA, 'authorized-owner');

    $assignment = OwnerContext::withOwner($ownerA, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'subject_key' => 'anonymous:authorized-owner',
        'bucket' => 0,
        'assigned_at' => CarbonImmutable::now(),
        'first_exposed_at' => CarbonImmutable::now(),
        'last_seen_at' => CarbonImmutable::now(),
    ]));

    expect(fn (): array => OwnerContext::withOwner($ownerB, fn (): array => app(BuildExperimentSignalProperties::class)->handle($assignment)))
        ->toThrow(AuthorizationException::class, 'Assignment is not accessible in the current owner scope.');
});

it('rejects assignment context resolution when growth owner scoping is disabled and the experiment is outside the current tracked property owner scope', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $ownerA = growthSignalPropertiesOwner();
    $ownerB = growthSignalPropertiesOwner();

    [, $experiment, $variant] = growthSignalPropertiesExperiment($ownerA, 'owner-disabled-foreign-assignment');
    $assignmentId = (string) Str::uuid();
    $timestamp = CarbonImmutable::now();

    DB::table((new Assignment)->getTable())->insert([
        'id' => $assignmentId,
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'signal_identity_id' => null,
        'signal_session_id' => null,
        'subject_key' => 'anonymous:owner-disabled-foreign-assignment',
        'bucket' => 0,
        'metadata' => json_encode([]),
        'assigned_at' => $timestamp,
        'first_exposed_at' => $timestamp,
        'last_seen_at' => $timestamp,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => (string) $ownerA->getKey(),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $assignment = Assignment::query()->findOrFail($assignmentId);

    expect(fn (): array => OwnerContext::withOwner($ownerB, fn (): array => app(BuildExperimentSignalProperties::class)->handle($assignment)))
        ->toThrow(AuthorizationException::class, 'Assignment is not accessible in the current owner scope.');
});

it('rejects assignment context resolution when the stored variant does not belong to the stored experiment', function (): void {
    $owner = growthSignalPropertiesOwner();

    [, $experimentA] = growthSignalPropertiesExperiment($owner, 'alpha');
    [, $experimentB, $variantB] = growthSignalPropertiesExperiment($owner, 'beta');

    $assignmentId = (string) Str::uuid();
    $timestamp = CarbonImmutable::now();

    DB::table((new Assignment)->getTable())->insert([
        'id' => $assignmentId,
        'experiment_id' => $experimentA->getKey(),
        'variant_id' => $variantB->getKey(),
        'signal_identity_id' => null,
        'signal_session_id' => null,
        'subject_key' => 'anonymous:corrupt-variant-parent',
        'bucket' => 0,
        'metadata' => json_encode([]),
        'assigned_at' => $timestamp,
        'first_exposed_at' => $timestamp,
        'last_seen_at' => $timestamp,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => (string) $owner->getKey(),
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $assignment = OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->findOrFail($assignmentId));

    expect(fn (): array => OwnerContext::withOwner($owner, fn (): array => app(BuildExperimentSignalProperties::class)->handle($assignment)))
        ->toThrow(InvalidArgumentException::class, 'Assignment experiment context could not be resolved.');
});

it('rejects assignment context resolution when the experiment tracked property drifts outside the experiment owner tuple', function (): void {
    $ownerA = growthSignalPropertiesOwner();
    $ownerB = growthSignalPropertiesOwner();

    [, $experiment, $variant] = growthSignalPropertiesExperiment($ownerA, 'drifted-property');

    $assignment = OwnerContext::withOwner($ownerA, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'subject_key' => 'anonymous:drifted-property',
        'bucket' => 0,
        'assigned_at' => CarbonImmutable::now(),
        'first_exposed_at' => CarbonImmutable::now(),
        'last_seen_at' => CarbonImmutable::now(),
    ]));

    $foreignTrackedProperty = OwnerContext::withOwner($ownerB, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Foreign Drifted Signal Property ' . Str::random(6),
        'slug' => 'foreign-drifted-signal-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));

    DB::table($experiment->getTable())
        ->where('id', $experiment->getKey())
        ->update(['tracked_property_id' => $foreignTrackedProperty->getKey()]);

    expect(fn (): array => OwnerContext::withOwner($ownerA, fn (): array => app(BuildExperimentSignalProperties::class)->handle($assignment)))
        ->toThrow(AuthorizationException::class, 'Assignment is not accessible in the current owner scope.');
});
