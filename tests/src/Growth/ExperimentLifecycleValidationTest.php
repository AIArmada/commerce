<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\SaveExperiment;
use AIArmada\Growth\Actions\SaveVariant;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Enums\VariantStatus;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

function lifecycleOwner(): User
{
    return User::query()->create([
        'name' => 'Lifecycle Owner ' . Str::random(6),
        'email' => 'lifecycle-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function lifecycleProperty(User $owner): TrackedProperty
{
    return OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Lifecycle Property ' . Str::random(6),
        'slug' => 'lifecycle-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));
}

function lifecycleExperiment(User $owner, TrackedProperty $property, array $overrides = []): Experiment
{
    return OwnerContext::withOwner($owner, fn (): Experiment => app(SaveExperiment::class)->create(array_replace([
        'tracked_property_id' => $property->getKey(),
        'name' => 'Lifecycle Experiment ' . Str::random(6),
        'slug' => 'lifecycle-experiment-' . Str::lower(Str::random(8)),
        'status' => ExperimentStatus::Active->value,
    ], $overrides)));
}

function lifecycleVariant(User $owner, Experiment $experiment): Variant
{
    return OwnerContext::withOwner($owner, fn (): Variant => app(SaveVariant::class)->create([
        'experiment_id' => $experiment->getKey(),
        'code' => Str::upper(Str::random(1)),
        'name' => 'Lifecycle Variant ' . Str::random(6),
        'traffic_percentage' => 100,
        'position' => 1,
        'is_control' => true,
        'status' => VariantStatus::Active->value,
    ]));
}

describe('Growth lifecycle rules', function (): void {
    beforeEach(function (): void {
        config()->set('growth.features.owner.enabled', true);
        config()->set('signals.owner.enabled', true);
    });

    it('derives experiment ownership from the writable tracked property when auto assignment is disabled', function (): void {
        config()->set('growth.features.owner.auto_assign_on_create', false);

        $owner = lifecycleOwner();
        $property = lifecycleProperty($owner);
        $experiment = lifecycleExperiment($owner, $property);

        expect((string) $experiment->owner_type)->toBe($owner->getMorphClass())
            ->and((string) $experiment->owner_id)->toBe((string) $owner->getKey());
    });

    it('rejects invalid experiment status input as validation errors', function (): void {
        $owner = lifecycleOwner();
        $property = lifecycleProperty($owner);
        $experiment = lifecycleExperiment($owner, $property);

        expect(fn (): Experiment => lifecycleExperiment($owner, $property, ['status' => 'invalid']))
            ->toThrow(ValidationException::class);

        expect(fn (): Experiment => OwnerContext::withOwner(
            $owner,
            fn (): Experiment => app(SaveExperiment::class)->update((string) $experiment->getKey(), ['status' => 'invalid']),
        ))->toThrow(ValidationException::class);
    });

    it('allows concluded experiments to archive but prevents reopening terminal states', function (): void {
        $owner = lifecycleOwner();
        $property = lifecycleProperty($owner);
        $experiment = lifecycleExperiment($owner, $property, ['status' => ExperimentStatus::Concluded->value]);

        $archived = OwnerContext::withOwner(
            $owner,
            fn (): Experiment => app(SaveExperiment::class)->update((string) $experiment->getKey(), ['status' => ExperimentStatus::Archived->value]),
        );

        expect($archived->status)->toBe(ExperimentStatus::Archived)
            ->and($archived->archived_at)->not->toBeNull();

        expect(fn (): Experiment => OwnerContext::withOwner($owner, function () use ($experiment): Experiment {
            $archivedExperiment = Experiment::query()->findOrFail($experiment->getKey());

            return $archivedExperiment->transitionTo(ExperimentStatus::Active);
        }))->toThrow(InvalidArgumentException::class, 'A concluded or archived experiment cannot be reopened.');
    });

    it('clears the paused timestamp when an experiment returns to draft', function (): void {
        $owner = lifecycleOwner();
        $experiment = lifecycleExperiment($owner, lifecycleProperty($owner), ['status' => ExperimentStatus::Paused->value]);

        expect($experiment->paused_at)->not->toBeNull();

        $draft = OwnerContext::withOwner(
            $owner,
            fn (): Experiment => app(SaveExperiment::class)->update((string) $experiment->getKey(), ['status' => ExperimentStatus::Draft->value]),
        );

        expect($draft->status)->toBe(ExperimentStatus::Draft)
            ->and($draft->paused_at)->toBeNull();
    });

    it('maintains variant toggle and terminal timestamps while blocking archived reopen', function (): void {
        $owner = lifecycleOwner();
        $property = lifecycleProperty($owner);
        $experiment = lifecycleExperiment($owner, $property);
        $variant = lifecycleVariant($owner, $experiment);

        expect($variant->status)->toBe(VariantStatus::Active)
            ->and($variant->activated_at)->not->toBeNull()
            ->and($variant->deactivated_at)->toBeNull();

        OwnerContext::withOwner($owner, fn (): Variant => app(SaveVariant::class)->update((string) $variant->getKey(), ['status' => VariantStatus::Deactivated->value]));
        $variant->refresh();

        expect($variant->status)->toBe(VariantStatus::Deactivated)
            ->and($variant->deactivated_at)->not->toBeNull();

        OwnerContext::withOwner($owner, fn (): Variant => app(SaveVariant::class)->update((string) $variant->getKey(), ['status' => VariantStatus::Active->value]));
        $variant->refresh();

        expect($variant->status)->toBe(VariantStatus::Active)
            ->and($variant->deactivated_at)->toBeNull();

        OwnerContext::withOwner($owner, fn (): Variant => app(SaveVariant::class)->update((string) $variant->getKey(), ['status' => VariantStatus::Deactivated->value]));
        OwnerContext::withOwner($owner, fn (): Variant => app(SaveVariant::class)->update((string) $variant->getKey(), ['status' => VariantStatus::Retired->value]));
        $variant->refresh();

        expect($variant->status)->toBe(VariantStatus::Retired)
            ->and($variant->deactivated_at)->toBeNull()
            ->and($variant->retired_at)->not->toBeNull();

        OwnerContext::withOwner($owner, fn (): Variant => app(SaveVariant::class)->update((string) $variant->getKey(), ['status' => VariantStatus::Archived->value]));
        $variant->refresh();

        expect($variant->status)->toBe(VariantStatus::Archived)
            ->and($variant->archived_at)->not->toBeNull();

        expect(fn (): Variant => OwnerContext::withOwner(
            $owner,
            fn (): Variant => app(SaveVariant::class)->update((string) $variant->getKey(), ['status' => VariantStatus::Active->value]),
        ))->toThrow(ValidationException::class);
    });

    it('rejects invalid variant status input as validation errors', function (): void {
        $owner = lifecycleOwner();
        $property = lifecycleProperty($owner);
        $experiment = lifecycleExperiment($owner, $property);

        expect(fn (): Variant => OwnerContext::withOwner($owner, fn (): Variant => app(SaveVariant::class)->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'X',
            'name' => 'Invalid Status Variant',
            'traffic_percentage' => 100,
            'position' => 1,
            'status' => 'invalid',
        ])))->toThrow(ValidationException::class);
    });
});
