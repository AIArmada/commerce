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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
    return OwnerContext::withOwner($owner, function () use ($owner): TrackedProperty {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Growth Resolve Property ' . Str::random(6),
            'slug' => 'growth-resolve-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        if (! config('signals.owner.enabled', true)) {
            $trackedProperty->owner_type = $owner->getMorphClass();
            $trackedProperty->owner_id = (string) $owner->getKey();
            $trackedProperty->save();
        }

        return $trackedProperty;
    });
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

function growthCreateGlobalExperimentContext(ExperimentStatus $status = ExperimentStatus::Active): array
{
    return OwnerContext::withOwner(null, function () use ($status): array {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Growth Global Resolve Property ' . Str::random(6),
            'slug' => 'growth-global-resolve-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
            'owner_type' => null,
            'owner_id' => null,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->global()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'status' => $status,
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->global()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'G',
            'name' => 'Global Control',
            'traffic_percentage' => 100,
            'position' => 1,
            'is_control' => true,
        ]);

        return [$experiment->fresh(['variants', 'trackedProperty']) ?? $experiment, $variant];
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

it('allows resolving assignments without an owner context when growth owner scoping is disabled', function (): void {
    config()->set('growth.features.owner.enabled', false);
    config()->set('signals.owner.enabled', false);

    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $action = app(ResolveExperimentAssignment::class);

    $assignment = $action->handle($experiment, anonymousId: 'owner-disabled-assignment');

    expect($assignment)->toBeInstanceOf(Assignment::class)
        ->and($assignment->experiment_id)->toBe((string) $experiment->getKey());
});

it('hashes oversized anonymous identifiers into deterministic assignment subject keys', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $action = app(ResolveExperimentAssignment::class);
    $anonymousId = str_repeat('oversized-anonymous-id-', 20);
    $expectedSubjectKey = 'anonymous:sha256:' . hash('sha256', $anonymousId);

    $firstAssignment = OwnerContext::withOwner($owner, fn (): Assignment => $action->handle($experiment, anonymousId: $anonymousId));
    $secondAssignment = OwnerContext::withOwner($owner, fn (): Assignment => $action->handle($experiment, anonymousId: $anonymousId));

    expect($firstAssignment->subject_key)->toBe($expectedSubjectKey)
        ->and(mb_strlen($firstAssignment->subject_key))->toBeLessThanOrEqual(255)
        ->and(str_starts_with($firstAssignment->subject_key, 'anonymous:sha256:'))->toBeTrue()
        ->and($secondAssignment->getKey())->toBe($firstAssignment->getKey());
});

it('rejects resolving assignments for experiments tied to a foreign tracked property when growth owner scoping is disabled but signals owner scoping remains enabled', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $ownerA = growthCreateOwner();
    $ownerB = growthCreateOwner();
    $experiment = growthCreateExperiment($ownerA);
    $action = app(ResolveExperimentAssignment::class);

    expect(fn (): Assignment => OwnerContext::withOwner($ownerB, fn (): Assignment => $action->handle($experiment, anonymousId: 'foreign-tracked-property')))
        ->toThrow(AuthorizationException::class, 'Growth experiment is not accessible in the current owner scope.');
});

it('rejects resolving assignments when an experiment tracked property drifts outside the experiment owner tuple', function (): void {
    $ownerA = growthCreateOwner();
    $ownerB = growthCreateOwner();
    $experiment = growthCreateExperiment($ownerA);
    $action = app(ResolveExperimentAssignment::class);

    $foreignTrackedProperty = OwnerContext::withOwner($ownerB, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Foreign Drifted Resolve Property ' . Str::random(6),
        'slug' => 'foreign-drifted-resolve-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));

    DB::table($experiment->getTable())
        ->where('id', $experiment->getKey())
        ->update(['tracked_property_id' => $foreignTrackedProperty->getKey()]);

    expect(fn (): Assignment => OwnerContext::withOwner($ownerA, fn (): Assignment => $action->handle($experiment, anonymousId: 'drifted-tracked-property')))
        ->toThrow(AuthorizationException::class, 'Growth experiment is not accessible in the current owner scope.');
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

it('stores the bucket used to choose the assigned variant even if weights change before persistence', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $action = app(ResolveExperimentAssignment::class);
    /** @var Collection<int, Variant> $variants */
    $variants = $experiment->variants->sortBy('position')->values();
    $variantToMutate = $variants->firstWhere('code', 'A');

    expect($variantToMutate)->toBeInstanceOf(Variant::class);

    $originalTotalWeight = $variants->sum(fn (Variant $variant): int => (int) $variant->traffic_percentage);
    $mutatedTotalWeight = $originalTotalWeight - (int) $variantToMutate->traffic_percentage + 5;
    $anonymousId = null;
    $expectedBucket = null;

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $candidateAnonymousId = 'bucket-consistency-' . Str::lower(Str::random(8));
        $subjectKey = 'anonymous:' . $candidateAnonymousId;
        $hash = (int) sprintf('%u', crc32((string) $experiment->getKey() . '|' . $subjectKey));
        $originalBucket = $hash % $originalTotalWeight;
        $mutatedBucket = $hash % $mutatedTotalWeight;

        if ($originalBucket === $mutatedBucket) {
            continue;
        }

        $anonymousId = $candidateAnonymousId;
        $expectedBucket = $originalBucket;

        break;
    }

    if (! is_string($anonymousId) || ! is_int($expectedBucket)) {
        throw new RuntimeException('Unable to find a bucket scenario that changes when variant weights mutate.');
    }

    $cursor = 0;
    $expectedVariant = null;

    foreach ($variants as $variant) {
        $cursor += (int) $variant->traffic_percentage;

        if ($expectedBucket < $cursor) {
            $expectedVariant = $variant;

            break;
        }
    }

    $expectedVariant ??= $variants->last();

    if (! $expectedVariant instanceof Variant) {
        throw new RuntimeException('Unable to determine the expected variant for the calculated bucket.');
    }

    $mutated = false;
    $variantTable = mb_strtolower($variantToMutate->getTable());

    DB::listen(function ($query) use (&$mutated, $variantTable, $variantToMutate): void {
        if ($mutated) {
            return;
        }

        $sql = mb_strtolower($query->sql);

        if (! str_contains($sql, $variantTable) || ! str_contains($sql, 'order by')) {
            return;
        }

        $mutated = true;

        Variant::query()
            ->withoutOwnerScope()
            ->whereKey($variantToMutate->getKey())
            ->update(['traffic_percentage' => 5]);
    });

    $assignment = OwnerContext::withOwner($owner, fn (): Assignment => $action->handle($experiment, anonymousId: $anonymousId));

    expect($assignment->bucket)->toBe($expectedBucket)
        ->and($assignment->variant_id)->toBe((string) $expectedVariant->getKey());
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

it('rejects resolving assignments for experiments outside the current owner scope', function (): void {
    $ownerA = growthCreateOwner();
    $ownerB = growthCreateOwner();
    $experiment = growthCreateExperiment($ownerA);
    $action = app(ResolveExperimentAssignment::class);

    expect(fn (): Assignment => OwnerContext::withOwner($ownerB, fn (): Assignment => $action->handle($experiment, anonymousId: 'outsider-1')))
        ->toThrow(AuthorizationException::class, 'Growth experiment is not accessible in the current owner scope.');
});

it('rejects resolving assignments with a signal identity from another owner when signals owner scoping is disabled', function (): void {
    config()->set('signals.owner.enabled', false);

    $ownerA = growthCreateOwner();
    $ownerB = growthCreateOwner();
    $experiment = growthCreateExperiment($ownerA);
    $otherTrackedProperty = growthCreateTrackedProperty($ownerB);
    $foreignIdentity = growthCreateIdentity($otherTrackedProperty, $ownerB);

    DB::table($foreignIdentity->getTable())
        ->where('id', $foreignIdentity->getKey())
        ->update([
            'tracked_property_id' => $experiment->tracked_property_id,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);

    $action = app(ResolveExperimentAssignment::class);

    expect(fn (): Assignment => OwnerContext::withOwner($ownerA, fn (): Assignment => $action->handle($experiment, $foreignIdentity)))
        ->toThrow(AuthorizationException::class, 'Signal identity is not accessible in the current owner scope.');
});

it('rejects resolving assignments for global experiments outside explicit global context', function (): void {
    config()->set('growth.features.owner.include_global', true);

    $owner = growthCreateOwner();
    [$experiment] = growthCreateGlobalExperimentContext();
    $action = app(ResolveExperimentAssignment::class);

    expect(fn (): Assignment => OwnerContext::withOwner($owner, fn (): Assignment => $action->handle($experiment, anonymousId: 'global-experiment-attempt')))
        ->toThrow(AuthorizationException::class, 'Explicit global owner context is required to resolve assignments for global growth experiments.');
});

it('resolves assignments for global experiments inside explicit global context', function (): void {
    config()->set('growth.features.owner.include_global', true);

    [$experiment, $variant] = growthCreateGlobalExperimentContext();
    $action = app(ResolveExperimentAssignment::class);

    $assignment = OwnerContext::withOwner(null, fn (): Assignment => $action->handle($experiment, anonymousId: 'global-explicit'));

    expect($assignment->isGlobal())->toBeTrue()
        ->and($assignment->experiment_id)->toBe((string) $experiment->getKey())
        ->and($assignment->variant_id)->toBe((string) $variant->getKey());
});

it('rejects direct assignment creation against a global experiment from a tenant context', function (): void {
    config()->set('growth.features.owner.include_global', true);

    $owner = growthCreateOwner();
    [$experiment, $variant] = growthCreateGlobalExperimentContext();

    expect(fn (): Assignment => OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'subject_key' => 'anonymous:tenant-global-attempt',
        'bucket' => 0,
        'assigned_at' => CarbonImmutable::now(),
        'first_exposed_at' => CarbonImmutable::now(),
        'last_seen_at' => CarbonImmutable::now(),
    ])))->toThrow(InvalidArgumentException::class, 'Assignment owner must match the parent experiment owner.');
});

it('rejects direct assignment creation against a foreign tracked property experiment when growth owner scoping is disabled but signals owner scoping remains enabled', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $ownerA = growthCreateOwner();
    $ownerB = growthCreateOwner();
    $experiment = growthCreateExperiment($ownerA);
    $variant = $experiment->variants->firstWhere('code', 'A');

    expect($variant)->toBeInstanceOf(Variant::class);

    expect(fn (): Assignment => OwnerContext::withOwner($ownerB, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'subject_key' => 'anonymous:foreign-tracked-property',
        'bucket' => 0,
        'assigned_at' => CarbonImmutable::now(),
        'first_exposed_at' => CarbonImmutable::now(),
        'last_seen_at' => CarbonImmutable::now(),
    ])))->toThrow(AuthorizationException::class, 'Assignment experiment is not accessible in the current owner scope.');
});

it('rejects resolving assignments with an identity from another tracked property', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $otherTrackedProperty = growthCreateTrackedProperty($owner);
    $foreignIdentity = growthCreateIdentity($otherTrackedProperty, $owner);
    $action = app(ResolveExperimentAssignment::class);

    expect(fn (): Assignment => OwnerContext::withOwner($owner, fn (): Assignment => $action->handle($experiment, $foreignIdentity)))
        ->toThrow(InvalidArgumentException::class, 'Signal identity must belong to the same tracked property as the experiment.');

    expect(OwnerContext::withOwner($owner, fn (): int => Assignment::query()->count()))->toBe(0);
});

it('rejects resolving assignments with a session from another tracked property', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $otherTrackedProperty = growthCreateTrackedProperty($owner);
    $foreignSession = growthCreateSession($otherTrackedProperty, $owner);
    $action = app(ResolveExperimentAssignment::class);

    expect(fn (): Assignment => OwnerContext::withOwner($owner, fn (): Assignment => $action->handle($experiment, null, $foreignSession)))
        ->toThrow(InvalidArgumentException::class, 'Signal session must belong to the same tracked property as the experiment.');

    expect(OwnerContext::withOwner($owner, fn (): int => Assignment::query()->count()))->toBe(0);
});

it('rejects direct assignment creation with a signal identity from another tracked property', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $variant = $experiment->variants->firstWhere('code', 'A');
    $otherTrackedProperty = growthCreateTrackedProperty($owner);
    $foreignIdentity = growthCreateIdentity($otherTrackedProperty, $owner);

    expect($variant)->toBeInstanceOf(Variant::class);

    expect(fn (): Assignment => OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'signal_identity_id' => $foreignIdentity->getKey(),
        'subject_key' => 'identity:' . (string) $foreignIdentity->getKey(),
        'bucket' => 0,
        'assigned_at' => CarbonImmutable::now(),
        'first_exposed_at' => CarbonImmutable::now(),
        'last_seen_at' => CarbonImmutable::now(),
    ])))->toThrow(InvalidArgumentException::class, 'Assignment signal identity must belong to the same tracked property as the experiment.');
});

it('rejects direct assignment creation with a signal identity from another owner when signals owner scoping is disabled', function (): void {
    config()->set('signals.owner.enabled', false);

    $ownerA = growthCreateOwner();
    $ownerB = growthCreateOwner();
    $experiment = growthCreateExperiment($ownerA);
    $variant = $experiment->variants->firstWhere('code', 'A');
    $otherTrackedProperty = growthCreateTrackedProperty($ownerB);
    $foreignIdentity = growthCreateIdentity($otherTrackedProperty, $ownerB);

    expect($variant)->toBeInstanceOf(Variant::class);

    DB::table($foreignIdentity->getTable())
        ->where('id', $foreignIdentity->getKey())
        ->update([
            'tracked_property_id' => $experiment->tracked_property_id,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);

    expect(fn (): Assignment => OwnerContext::withOwner($ownerA, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'signal_identity_id' => $foreignIdentity->getKey(),
        'subject_key' => 'identity:' . (string) $foreignIdentity->getKey(),
        'bucket' => 0,
        'assigned_at' => CarbonImmutable::now(),
        'first_exposed_at' => CarbonImmutable::now(),
        'last_seen_at' => CarbonImmutable::now(),
    ])))->toThrow(AuthorizationException::class, 'Assignment signal identity is not accessible in the current owner scope.');
});

it('rejects direct assignment creation with a signal session from another tracked property', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $variant = $experiment->variants->firstWhere('code', 'A');
    $otherTrackedProperty = growthCreateTrackedProperty($owner);
    $foreignSession = growthCreateSession($otherTrackedProperty, $owner);

    expect($variant)->toBeInstanceOf(Variant::class);

    expect(fn (): Assignment => OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'signal_session_id' => $foreignSession->getKey(),
        'subject_key' => 'session:' . (string) $foreignSession->getKey(),
        'bucket' => 0,
        'assigned_at' => CarbonImmutable::now(),
        'first_exposed_at' => CarbonImmutable::now(),
        'last_seen_at' => CarbonImmutable::now(),
    ])))->toThrow(InvalidArgumentException::class, 'Assignment signal session must belong to the same tracked property as the experiment.');
});

it('rejects resolving assignments when the provided session belongs to another identity', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $identityA = growthCreateIdentity($experiment->trackedProperty, $owner);
    $identityB = growthCreateIdentity($experiment->trackedProperty, $owner);
    $session = growthCreateSession($experiment->trackedProperty, $owner, $identityB);
    $action = app(ResolveExperimentAssignment::class);

    expect(fn (): Assignment => OwnerContext::withOwner($owner, fn (): Assignment => $action->handle($experiment, $identityA, $session)))
        ->toThrow(InvalidArgumentException::class, 'Signal session must match the provided signal identity.');
});

it('rejects direct assignment creation when the signal session belongs to another identity', function (): void {
    $owner = growthCreateOwner();
    $experiment = growthCreateExperiment($owner);
    $variant = $experiment->variants->firstWhere('code', 'A');
    $identityA = growthCreateIdentity($experiment->trackedProperty, $owner);
    $identityB = growthCreateIdentity($experiment->trackedProperty, $owner);
    $session = growthCreateSession($experiment->trackedProperty, $owner, $identityB);

    expect($variant)->toBeInstanceOf(Variant::class);

    expect(fn (): Assignment => OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experiment->getKey(),
        'variant_id' => $variant->getKey(),
        'signal_identity_id' => $identityA->getKey(),
        'signal_session_id' => $session->getKey(),
        'subject_key' => 'identity:' . (string) $identityA->getKey(),
        'bucket' => 0,
        'assigned_at' => CarbonImmutable::now(),
        'first_exposed_at' => CarbonImmutable::now(),
        'last_seen_at' => CarbonImmutable::now(),
    ])))->toThrow(InvalidArgumentException::class, 'Assignment signal session must match the provided signal identity.');
});

it('rejects changing a persisted assignment experiment or variant after creation', function (): void {
    $owner = growthCreateOwner();
    $experimentA = growthCreateExperiment($owner);
    $experimentB = growthCreateExperiment($owner);
    $variantA = $experimentA->variants->firstWhere('code', 'A');
    $variantB = $experimentA->variants->firstWhere('code', 'B');

    expect($variantA)->toBeInstanceOf(Variant::class)
        ->and($variantB)->toBeInstanceOf(Variant::class);

    $assignment = OwnerContext::withOwner($owner, fn (): Assignment => Assignment::query()->create([
        'experiment_id' => $experimentA->getKey(),
        'variant_id' => $variantA->getKey(),
        'subject_key' => 'anonymous:immutable-assignment',
        'bucket' => 0,
        'assigned_at' => CarbonImmutable::now(),
        'first_exposed_at' => CarbonImmutable::now(),
        'last_seen_at' => CarbonImmutable::now(),
    ]));

    expect(fn (): bool => OwnerContext::withOwner($owner, function () use ($assignment, $experimentB): bool {
        $assignment->experiment_id = (string) $experimentB->getKey();

        return $assignment->save();
    }))->toThrow(InvalidArgumentException::class, 'Assignment experiment_id cannot be changed after creation.');

    $freshAssignment = $assignment->fresh() ?? $assignment;

    expect(fn (): bool => OwnerContext::withOwner($owner, function () use ($freshAssignment, $variantB): bool {
        $freshAssignment->variant_id = (string) $variantB->getKey();

        return $freshAssignment->save();
    }))->toThrow(InvalidArgumentException::class, 'Assignment variant_id cannot be changed after creation.');
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
