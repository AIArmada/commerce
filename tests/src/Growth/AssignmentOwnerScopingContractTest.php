<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$createOwner = function (): Model {
    return User::query()->create([
        'name' => 'Assignment Owner ' . Str::random(6),
        'email' => 'assignment-owner-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
};

$createExperimentAndVariant = function (?Model $owner): array {
    return OwnerContext::withOwner($owner, function () use ($owner): array {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Assignment Property ' . Str::random(6),
            'slug' => 'assignment-property-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
        ]);

        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
        ]);

        $variant = Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'ASSIGN' . Str::upper(Str::random(3)),
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
        ]);

        return [$experiment, $variant];
    });
};

$createModelForOwner = function (Model $owner) use ($createExperimentAndVariant): Assignment {
    return OwnerContext::withOwner($owner, function () use ($owner, $createExperimentAndVariant): Assignment {
        [$experiment, $variant] = $createExperimentAndVariant($owner);

        return Assignment::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variant->getKey(),
            'subject_key' => 'identity:' . Str::uuid()->toString(),
            'assigned_at' => CarbonImmutable::now(),
        ]);
    });
};

$createGlobalModel = function () use ($createExperimentAndVariant): Assignment {
    return OwnerContext::withOwner(null, function () use ($createExperimentAndVariant): Assignment {
        [$experiment, $variant] = $createExperimentAndVariant(null);

        return Assignment::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variant->getKey(),
            'subject_key' => 'identity:' . Str::uuid()->toString(),
            'assigned_at' => CarbonImmutable::now(),
            'owner_type' => null,
            'owner_id' => null,
        ]);
    });
};

describe('AssignmentOwnerScopingContract', function () use ($createOwner, $createExperimentAndVariant, $createModelForOwner, $createGlobalModel): void {
    it('model uses has owner trait', function (): void {
        expect(class_uses_recursive(Assignment::class))->toContain(HasOwner::class);
    });

    it('model can be assigned owner', function () use ($createOwner, $createModelForOwner): void {
        $owner = $createOwner();
        $model = $createModelForOwner($owner);

        expect($model->hasOwner())->toBeTrue()
            ->and($model->belongsToOwner($owner))->toBeTrue();
    });

    it('model can be global', function () use ($createGlobalModel): void {
        $model = $createGlobalModel();

        expect($model->isGlobal())->toBeTrue()
            ->and($model->hasOwner())->toBeFalse();
    });

    it('for owner scope filters by owner', function () use ($createOwner, $createModelForOwner): void {
        $owner1 = $createOwner();
        $owner2 = $createOwner();

        $model1 = $createModelForOwner($owner1);
        $createModelForOwner($owner2);

        $results = Assignment::withoutGlobalScopes()
            ->forOwner($owner1)
            ->get();

        expect($results)->toHaveCount(1)
            ->and($results->first()->getKey())->toBe($model1->getKey());
    });

    it('for owner with include global includes global records', function () use ($createOwner, $createModelForOwner, $createGlobalModel): void {
        $owner = $createOwner();

        $ownedModel = $createModelForOwner($owner);
        $globalModel = $createGlobalModel();

        $results = Assignment::withoutGlobalScopes()
            ->forOwner($owner, includeGlobal: true)
            ->get();

        expect($results)->toHaveCount(2)
            ->and($results->pluck($ownedModel->getKeyName())->toArray())
            ->toContain($ownedModel->getKey())
            ->toContain($globalModel->getKey());
    });

    it('global only scope returns only global records', function () use ($createOwner, $createModelForOwner, $createGlobalModel): void {
        $owner = $createOwner();

        $createModelForOwner($owner);
        $globalModel = $createGlobalModel();

        $results = Assignment::withoutGlobalScopes()
            ->globalOnly()
            ->get();

        expect($results)->toHaveCount(1)
            ->and($results->first()->getKey())->toBe($globalModel->getKey());
    });

    it('owner context with owner scopes queries', function () use ($createOwner, $createModelForOwner): void {
        $owner1 = $createOwner();
        $owner2 = $createOwner();

        $model1 = $createModelForOwner($owner1);
        $createModelForOwner($owner2);

        $results = OwnerContext::withOwner($owner1, function () {
            return Assignment::withoutGlobalScopes()
                ->forOwner(OwnerContext::resolve())
                ->get();
        });

        expect($results)->toHaveCount(1)
            ->and($results->first()->getKey())->toBe($model1->getKey());
    });

    it('assign owner sets owner', function () use ($createOwner, $createGlobalModel): void {
        $owner = $createOwner();
        $model = $createGlobalModel();

        expect(fn () => $model->assignOwner($owner)->save())
            ->toThrow(InvalidArgumentException::class, 'Owner cannot be assigned to a persisted global');
    });

    it('remove owner throws on persisted owned record', function () use ($createOwner, $createModelForOwner): void {
        $owner = $createOwner();
        $model = $createModelForOwner($owner);

        expect($model->hasOwner())->toBeTrue();

        expect(fn () => $model->removeOwner())
            ->toThrow(InvalidArgumentException::class, 'Owner cannot be removed from a persisted');
    });

    it('remove owner allowed on unsaved model', function (): void {
        $model = new Assignment;
        $model->removeOwner();

        expect($model->isGlobal())->toBeTrue();
    });

    it('cross tenant access prevented', function () use ($createOwner, $createModelForOwner): void {
        $owner1 = $createOwner();
        $owner2 = $createOwner();

        $model1 = $createModelForOwner($owner1);

        $results = Assignment::withoutGlobalScopes()
            ->forOwner($owner2)
            ->where($model1->getKeyName(), $model1->getKey())
            ->get();

        expect($results)->toBeEmpty();
    });

    it('persisted assignment revalidates the parent experiment on save', function () use ($createOwner, $createExperimentAndVariant): void {
        $ownerA = $createOwner();
        $ownerB = $createOwner();
        [$experimentA, $variantA] = $createExperimentAndVariant($ownerA);
        [$experimentB] = $createExperimentAndVariant($ownerB);

        $assignment = OwnerContext::withOwner($ownerA, fn (): Assignment => Assignment::factory()->create([
            'experiment_id' => $experimentA->getKey(),
            'variant_id' => $variantA->getKey(),
            'subject_key' => 'identity:' . Str::uuid()->toString(),
            'assigned_at' => CarbonImmutable::now(),
        ]));

        DB::table($assignment->getTable())
            ->where('id', $assignment->getKey())
            ->update(['experiment_id' => $experimentB->getKey()]);

        expect(fn (): bool => OwnerContext::withOwner($ownerA, function () use ($assignment): bool {
            $corruptAssignment = Assignment::query()->findOrFail($assignment->getKey());
            $corruptAssignment->subject_key = 'identity:' . Str::uuid()->toString();

            return $corruptAssignment->save();
        }))->toThrow(AuthorizationException::class, 'Assignment experiment is not accessible in the current owner scope.');
    });
});
