<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Growth;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Actions\ArchiveExperiments;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function archiveScopeOwner(string $label): User
{
    return User::query()->create([
        'name' => 'Archive Scope ' . $label . ' ' . Str::random(6),
        'email' => 'archive-scope-' . Str::lower($label) . '-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function archiveScopeProperty(User $owner, string $label): TrackedProperty
{
    return OwnerContext::withOwner($owner, fn (): TrackedProperty => TrackedProperty::query()->create([
        'name' => 'Archive Property ' . $label . ' ' . Str::random(6),
        'slug' => 'archive-property-' . Str::lower($label) . '-' . Str::lower(Str::random(8)),
        'write_key' => Str::random(40),
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]));
}

function archiveScopeExperiment(User $owner, TrackedProperty $property): Experiment
{
    return OwnerContext::withOwner($owner, fn (): Experiment => Experiment::factory()->create([
        'tracked_property_id' => $property->getKey(),
        'status' => ExperimentStatus::Concluded,
    ]));
}

describe('Archive experiment writable eligibility', function (): void {
    beforeEach(function (): void {
        config()->set('growth.features.owner.enabled', true);
        config()->set('signals.owner.enabled', true);

        app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);
    });

    it('archives eligible concluded experiments while skipping drifted property owners', function (bool $signalsOwnerEnabled): void {
        $ownerA = archiveScopeOwner('A');
        $ownerB = archiveScopeOwner('B');

        $propertyA = archiveScopeProperty($ownerA, 'A');
        $propertyB = archiveScopeProperty($ownerB, 'B');

        $eligible = archiveScopeExperiment($ownerA, $propertyA);
        $drifted = archiveScopeExperiment($ownerA, $propertyA);

        DB::table($drifted->getTable())
            ->where('id', $drifted->getKey())
            ->update([
                'tracked_property_id' => $propertyB->getKey(),
                'updated_at' => CarbonImmutable::now()->subDays(120),
            ]);

        DB::table($eligible->getTable())
            ->where('id', $eligible->getKey())
            ->update(['updated_at' => CarbonImmutable::now()->subDays(120)]);

        config()->set('signals.owner.enabled', $signalsOwnerEnabled);

        $archived = app(ArchiveExperiments::class)->handle(CarbonImmutable::now()->subDays(90), false);

        expect($archived)->toBe(1)
            ->and($eligible->fresh()?->status)->toBe(ExperimentStatus::Archived)
            ->and($drifted->fresh()?->status)->toBe(ExperimentStatus::Concluded);
    })->with([true, false]);

    it('reports the same eligible count in dry runs without persisting', function (): void {
        $owner = archiveScopeOwner('Dry');
        $property = archiveScopeProperty($owner, 'Dry');
        $experiment = archiveScopeExperiment($owner, $property);

        DB::table($experiment->getTable())
            ->where('id', $experiment->getKey())
            ->update(['updated_at' => CarbonImmutable::now()->subDays(120)]);

        $archived = app(ArchiveExperiments::class)->handle(CarbonImmutable::now()->subDays(90), true);

        expect($archived)->toBe(1)
            ->and($experiment->fresh()?->status)->toBe(ExperimentStatus::Concluded);
    });
});
