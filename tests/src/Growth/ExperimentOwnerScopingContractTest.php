<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

$createOwner = function (): Model {
    return User::query()->create([
        'name' => 'Growth Owner ' . Str::random(6),
        'email' => 'growth-owner-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
};

$createTrackedProperty = function (?Model $owner): TrackedProperty {
    return OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Growth Property ' . Str::random(6),
        'slug' => 'growth-property-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
        'owner_type' => $owner?->getMorphClass(),
        'owner_id' => $owner?->getKey(),
    ]));
};

$createModelForOwner = function (Model $owner) use ($createTrackedProperty): Experiment {
    return OwnerContext::withOwner($owner, function () use ($owner, $createTrackedProperty): Experiment {
        $trackedProperty = $createTrackedProperty($owner);

        return Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
        ]);
    });
};

$createGlobalModel = function () use ($createTrackedProperty): Experiment {
    return OwnerContext::withOwner(null, function () use ($createTrackedProperty): Experiment {
        $trackedProperty = $createTrackedProperty(null);

        return Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'owner_type' => null,
            'owner_id' => null,
        ]);
    });
};

describe('ExperimentOwnerScopingContract', function () use ($createOwner, $createModelForOwner, $createGlobalModel): void {
    it('model uses has owner trait', function (): void {
        expect(class_uses_recursive(Experiment::class))->toContain(HasOwner::class);
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

        $results = Experiment::withoutGlobalScopes()
            ->forOwner($owner1)
            ->get();

        expect($results)->toHaveCount(1)
            ->and($results->first()->getKey())->toBe($model1->getKey());
    });

    it('for owner with include global includes global records', function () use ($createOwner, $createModelForOwner, $createGlobalModel): void {
        $owner = $createOwner();

        $ownedModel = $createModelForOwner($owner);
        $globalModel = $createGlobalModel();

        $results = Experiment::withoutGlobalScopes()
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

        $results = Experiment::withoutGlobalScopes()
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
            return Experiment::withoutGlobalScopes()
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
        $model = new Experiment;
        $model->removeOwner();

        expect($model->isGlobal())->toBeTrue();
    });

    it('cross tenant access prevented', function () use ($createOwner, $createModelForOwner): void {
        $owner1 = $createOwner();
        $owner2 = $createOwner();

        $model1 = $createModelForOwner($owner1);

        $results = Experiment::withoutGlobalScopes()
            ->forOwner($owner2)
            ->where($model1->getKeyName(), $model1->getKey())
            ->get();

        expect($results)->toBeEmpty();
    });
});
