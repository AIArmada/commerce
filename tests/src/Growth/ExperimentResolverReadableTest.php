<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Enums\ResolveStrategy;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Support\Context\ExperimentResolver;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use InvalidArgumentException;

function readableOwner(): User
{
    return User::query()->create([
        'name' => 'Readable Owner ' . Str::random(6),
        'email' => 'readable-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function readableExperiment(User $owner, ExperimentStatus $status): Experiment
{
    return OwnerContext::withOwner($owner, function () use ($status): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Readable Property ' . Str::random(6),
            'slug' => 'readable-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'status' => $status,
        ]);

        return $experiment->fresh(['trackedProperty']) ?? $experiment;
    });
}

describe('Experiment resolver readable strategy', function (): void {
    beforeEach(function (): void {
        config()->set('growth.features.owner.enabled', true);
        config()->set('signals.owner.enabled', true);
    });

    it('rejects readable resolution of non-active experiments in owner scope', function (): void {
        $owner = readableOwner();
        $draft = readableExperiment($owner, ExperimentStatus::Draft);

        expect(fn (): Experiment => OwnerContext::withOwner(
            $owner,
            fn (): Experiment => app(ExperimentResolver::class)->resolve((string) $draft->getKey(), ResolveStrategy::Readable)
        ))->toThrow(AuthorizationException::class);
    });

    it('resolves non-active experiments for accessible access', function (): void {
        $owner = readableOwner();
        $draft = readableExperiment($owner, ExperimentStatus::Draft);

        $resolved = OwnerContext::withOwner(
            $owner,
            fn (): Experiment => app(ExperimentResolver::class)->resolve((string) $draft->getKey(), ResolveStrategy::Accessible)
        );

        expect((string) $resolved->getKey())->toBe((string) $draft->getKey());
    });

    it('resolves active experiments for readable access', function (): void {
        $owner = readableOwner();
        $active = readableExperiment($owner, ExperimentStatus::Active);

        $resolved = OwnerContext::withOwner(
            $owner,
            fn (): Experiment => app(ExperimentResolver::class)->resolve((string) $active->getKey(), ResolveStrategy::Readable)
        );

        expect((string) $resolved->getKey())->toBe((string) $active->getKey());
    });

    it('rejects readable resolution of non-active experiments without owner scoping', function (): void {
        config()->set('growth.features.owner.enabled', false);
        config()->set('signals.owner.enabled', false);

        $owner = readableOwner();
        $draft = readableExperiment($owner, ExperimentStatus::Draft);

        expect(fn (): Experiment => OwnerContext::withOwner(
            $owner,
            fn (): Experiment => app(ExperimentResolver::class)->resolve((string) $draft->getKey(), ResolveStrategy::Readable)
        ))->toThrow(InvalidArgumentException::class);
    });
});
