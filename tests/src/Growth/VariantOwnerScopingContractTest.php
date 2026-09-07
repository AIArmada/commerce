<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$createOwner = function (): Model {
    return User::query()->create([
        'name' => 'Variant Owner ' . Str::random(6),
        'email' => 'variant-owner-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
};

$createExperiment = function (?Model $owner): Experiment {
    return OwnerContext::withOwner($owner, function () use ($owner): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Variant Test Property ' . Str::random(6),
            'slug' => 'variant-test-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
        ]);

        return Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
        ]);
    });
};

$createModelForOwner = function (Model $owner) use ($createExperiment): Variant {
    return OwnerContext::withOwner($owner, function () use ($owner, $createExperiment): Variant {
        $experiment = $createExperiment($owner);

        return Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'VAR' . Str::upper(Str::random(3)),
        ]);
    });
};

$createGlobalModel = function () use ($createExperiment): Variant {
    return OwnerContext::withOwner(null, function () use ($createExperiment): Variant {
        $experiment = $createExperiment(null);

        return Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'VAR' . Str::upper(Str::random(3)),
            'owner_type' => null,
            'owner_id' => null,
        ]);
    });
};

describe('VariantOwnerScopingContract', function () use ($createOwner, $createExperiment, $createModelForOwner, $createGlobalModel): void {
    it('model uses has owner trait', function (): void {
        expect(class_uses_recursive(Variant::class))->toContain(HasOwner::class);
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

        $results = Variant::withoutGlobalScopes()
            ->forOwner($owner1)
            ->get();

        expect($results)->toHaveCount(1)
            ->and($results->first()->getKey())->toBe($model1->getKey());
    });

    it('for owner with include global includes global records', function () use ($createOwner, $createModelForOwner, $createGlobalModel): void {
        $owner = $createOwner();

        $ownedModel = $createModelForOwner($owner);
        $globalModel = $createGlobalModel();

        $results = Variant::withoutGlobalScopes()
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

        $results = Variant::withoutGlobalScopes()
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
            return Variant::withoutGlobalScopes()
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
        $model = new Variant;
        $model->removeOwner();

        expect($model->isGlobal())->toBeTrue();
    });

    it('cross tenant access prevented', function () use ($createOwner, $createModelForOwner): void {
        $owner1 = $createOwner();
        $owner2 = $createOwner();

        $model1 = $createModelForOwner($owner1);

        $results = Variant::withoutGlobalScopes()
            ->forOwner($owner2)
            ->where($model1->getKeyName(), $model1->getKey())
            ->get();

        expect($results)->toBeEmpty();
    });

    it('experiment id is immutable after creation', function () use ($createOwner, $createExperiment): void {
        $owner = $createOwner();
        $experimentA = $createExperiment($owner);
        $experimentB = $createExperiment($owner);

        $variant = OwnerContext::withOwner($owner, fn (): Variant => Variant::factory()->create([
            'experiment_id' => $experimentA->getKey(),
            'code' => 'IMM' . Str::upper(Str::random(3)),
        ]));

        expect(fn (): bool => OwnerContext::withOwner($owner, function () use ($experimentB, $variant): bool {
            $variant->experiment_id = (string) $experimentB->getKey();

            return $variant->save();
        }))->toThrow(InvalidArgumentException::class, 'Variant experiment_id cannot be changed after creation.');
    });

    it('foreign tracked property experiment is rejected when growth owner scoping is disabled', function () use ($createOwner, $createExperiment): void {
        config()->set('growth.features.owner.enabled', false);

        $ownerA = $createOwner();
        $ownerB = $createOwner();
        $experiment = $createExperiment($ownerA);

        expect(fn (): Variant => OwnerContext::withOwner($ownerB, fn (): Variant => Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'FOR' . Str::upper(Str::random(3)),
        ])))->toThrow(AuthorizationException::class, 'Variant experiment is not accessible in the current owner scope.');
    });

    it('persisted variant revalidates the parent experiment on save', function () use ($createOwner, $createExperiment): void {
        $ownerA = $createOwner();
        $ownerB = $createOwner();
        $experimentA = $createExperiment($ownerA);
        $experimentB = $createExperiment($ownerB);

        $variant = OwnerContext::withOwner($ownerA, fn (): Variant => Variant::factory()->create([
            'experiment_id' => $experimentA->getKey(),
            'code' => 'DRV' . Str::upper(Str::random(3)),
        ]));

        DB::table($variant->getTable())
            ->where('id', $variant->getKey())
            ->update(['experiment_id' => $experimentB->getKey()]);

        expect(fn (): bool => OwnerContext::withOwner($ownerA, function () use ($variant): bool {
            $corruptVariant = Variant::query()->findOrFail($variant->getKey());
            $corruptVariant->name = 'Drifted Variant';

            return $corruptVariant->save();
        }))->toThrow(AuthorizationException::class, 'Variant experiment is not accessible in the current owner scope.');
    });
});
