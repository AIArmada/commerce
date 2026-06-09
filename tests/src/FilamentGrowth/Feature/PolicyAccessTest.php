<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentGrowth\Pages\ExperimentResultsPage;
use AIArmada\FilamentGrowth\Pages\GrowthDashboard;
use AIArmada\FilamentGrowth\Policies\ExperimentPolicy;
use AIArmada\FilamentGrowth\Policies\VariantPolicy;
use AIArmada\FilamentGrowth\Resources\ExperimentResource;
use AIArmada\FilamentGrowth\Resources\VariantResource;
use AIArmada\FilamentGrowth\Widgets\ExperimentWinnersWidget;
use AIArmada\FilamentGrowth\Widgets\GrowthStatsWidget;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

beforeEach(function (): void {
    filament()->setCurrentPanel('admin');

    Variant::query()->delete();
    Experiment::query()->delete();
    TrackedProperty::query()->delete();
});

function filamentGrowthPolicyUser(): User
{
    return User::query()->create([
        'name' => 'Growth Policy User ' . Str::random(6),
        'email' => 'growth-policy-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function filamentGrowthBindOwner(User $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly User $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

function filamentGrowthBindNoOwner(): void
{
    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });
}

function filamentGrowthCreateOwnedExperiment(User $owner, string $name = 'Authz Experiment'): array
{
    return OwnerContext::withOwner($owner, function () use ($name): array {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => $name . ' Property',
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'status' => 'active',
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'A',
            'name' => $name . ' Variant',
        ]);

        return [$experiment, $variant];
    });
}

function filamentGrowthCreateGlobalExperiment(string $name = 'Global Policy Experiment'): array
{
    return OwnerContext::withOwner(null, function () use ($name): array {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => $name . ' Property',
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'is_active' => true,
            'owner_type' => null,
            'owner_id' => null,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->global()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'status' => 'active',
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->global()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'G',
            'name' => $name . ' Variant',
        ]);

        return [$experiment, $variant];
    });
}

it('registers experiment and variant policies', function (): void {
    expect(Gate::getPolicyFor(Experiment::class))->toBeInstanceOf(ExperimentPolicy::class)
        ->and(Gate::getPolicyFor(Variant::class))->toBeInstanceOf(VariantPolicy::class);
});

it('allows authenticated users to manage experiments within the resolved owner scope', function (): void {
    $owner = filamentGrowthPolicyUser();
    $actor = filamentGrowthPolicyUser();

    filamentGrowthBindOwner($owner);
    [$experiment] = filamentGrowthCreateOwnedExperiment($owner);
    $this->actingAs($actor);

    OwnerContext::withOwner($owner, function () use ($experiment): void {
        expect(ExperimentResource::canCreate())->toBeTrue()
            ->and(ExperimentResource::canEdit($experiment))->toBeTrue()
            ->and(ExperimentResource::canDelete($experiment))->toBeTrue();
    });
});

it('allows bulk experiment and variant mutations when writable records exist', function (): void {
    $owner = filamentGrowthPolicyUser();
    $actor = filamentGrowthPolicyUser();

    filamentGrowthBindOwner($owner);
    filamentGrowthCreateOwnedExperiment($owner, 'Bulk Policy Experiment');
    $this->actingAs($actor);

    OwnerContext::withOwner($owner, function () use ($actor): void {
        expect(Gate::forUser($actor)->allows('deleteAny', Experiment::class))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('restoreAny', Experiment::class))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('forceDeleteAny', Experiment::class))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('deleteAny', Variant::class))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('restoreAny', Variant::class))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('forceDeleteAny', Variant::class))->toBeTrue();
    });
});

it('denies guest experiment resource mutations', function (): void {
    $owner = filamentGrowthPolicyUser();

    filamentGrowthBindOwner($owner);
    [$experiment] = filamentGrowthCreateOwnedExperiment($owner);
    auth()->logout();

    OwnerContext::withOwner($owner, function () use ($experiment): void {
        expect(ExperimentResource::canCreate())->toBeFalse()
            ->and(ExperimentResource::canEdit($experiment))->toBeFalse()
            ->and(ExperimentResource::canDelete($experiment))->toBeFalse();
    });
});

it('allows authenticated users to manage variants within the resolved owner scope', function (): void {
    $owner = filamentGrowthPolicyUser();
    $actor = filamentGrowthPolicyUser();

    filamentGrowthBindOwner($owner);
    [, $variant] = filamentGrowthCreateOwnedExperiment($owner, 'Variant Policy Experiment');
    $this->actingAs($actor);

    OwnerContext::withOwner($owner, function () use ($variant): void {
        expect(VariantResource::canCreate())->toBeTrue()
            ->and(VariantResource::canEdit($variant))->toBeTrue()
            ->and(VariantResource::canDelete($variant))->toBeTrue();
    });
});

it('denies guest variant resource mutations', function (): void {
    $owner = filamentGrowthPolicyUser();

    filamentGrowthBindOwner($owner);
    [, $variant] = filamentGrowthCreateOwnedExperiment($owner, 'Guest Variant Policy Experiment');
    auth()->logout();

    OwnerContext::withOwner($owner, function () use ($variant): void {
        expect(VariantResource::canCreate())->toBeFalse()
            ->and(VariantResource::canEdit($variant))->toBeFalse()
            ->and(VariantResource::canDelete($variant))->toBeFalse();
    });
});

it('allows authenticated users to access growth pages and widgets through experiment policies', function (): void {
    $owner = filamentGrowthPolicyUser();
    $actor = filamentGrowthPolicyUser();

    filamentGrowthBindOwner($owner);
    filamentGrowthCreateOwnedExperiment($owner, 'Page Policy Experiment');
    $this->actingAs($actor);

    OwnerContext::withOwner($owner, function () use ($actor): void {
        expect(Gate::forUser($actor)->allows('viewAny', Experiment::class))->toBeTrue()
            ->and(GrowthDashboard::canAccess())->toBeTrue()
            ->and(ExperimentResultsPage::canAccess())->toBeTrue()
            ->and(GrowthStatsWidget::canView())->toBeTrue()
            ->and(ExperimentWinnersWidget::canView())->toBeTrue();
    });
});

it('fails closed for growth pages and widgets when no Filament user is authenticated', function (): void {
    $owner = filamentGrowthPolicyUser();

    filamentGrowthBindOwner($owner);
    filamentGrowthCreateOwnedExperiment($owner, 'Guest Growth Policy Experiment');
    auth()->logout();

    OwnerContext::withOwner($owner, function (): void {
        expect(GrowthDashboard::canAccess())->toBeFalse()
            ->and(ExperimentResultsPage::canAccess())->toBeFalse()
            ->and(GrowthStatsWidget::canView())->toBeFalse()
            ->and(ExperimentWinnersWidget::canView())->toBeFalse();
    });
});

it('keeps policy authorization owner-aware for direct gate checks', function (): void {
    $ownerA = filamentGrowthPolicyUser();
    $ownerB = filamentGrowthPolicyUser();
    $actor = filamentGrowthPolicyUser();

    filamentGrowthBindOwner($ownerA);
    [$accessibleExperiment, $accessibleVariant] = filamentGrowthCreateOwnedExperiment($ownerA, 'Accessible Policy Experiment');
    [$foreignExperiment, $foreignVariant] = filamentGrowthCreateOwnedExperiment($ownerB, 'Foreign Policy Experiment');
    $this->actingAs($actor);

    OwnerContext::withOwner($ownerA, function () use ($actor, $accessibleExperiment, $accessibleVariant, $foreignExperiment, $foreignVariant): void {
        expect(Gate::forUser($actor)->allows('viewAny', Experiment::class))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('create', Experiment::class))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('view', $accessibleExperiment))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('update', $accessibleExperiment))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('delete', $accessibleExperiment))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('view', $foreignExperiment))->toBeFalse()
            ->and(Gate::forUser($actor)->allows('update', $foreignExperiment))->toBeFalse()
            ->and(Gate::forUser($actor)->allows('delete', $foreignExperiment))->toBeFalse()
            ->and(Gate::forUser($actor)->allows('viewAny', Variant::class))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('create', Variant::class))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('view', $accessibleVariant))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('update', $accessibleVariant))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('delete', $accessibleVariant))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('view', $foreignVariant))->toBeFalse()
            ->and(Gate::forUser($actor)->allows('update', $foreignVariant))->toBeFalse()
            ->and(Gate::forUser($actor)->allows('delete', $foreignVariant))->toBeFalse();
    });
});

it('denies bulk mutations when the resolved owner can only read global growth records', function (): void {
    config()->set('growth.features.owner.enabled', true);
    config()->set('growth.features.owner.include_global', true);

    $owner = filamentGrowthPolicyUser();
    $actor = filamentGrowthPolicyUser();

    filamentGrowthBindOwner($owner);
    filamentGrowthCreateGlobalExperiment('Readable Global Policy Experiment');
    $this->actingAs($actor);

    OwnerContext::withOwner($owner, function () use ($actor): void {
        expect(Gate::forUser($actor)->allows('deleteAny', Experiment::class))->toBeFalse()
            ->and(Gate::forUser($actor)->allows('restoreAny', Experiment::class))->toBeFalse()
            ->and(Gate::forUser($actor)->allows('forceDeleteAny', Experiment::class))->toBeFalse()
            ->and(Gate::forUser($actor)->allows('deleteAny', Variant::class))->toBeFalse()
            ->and(Gate::forUser($actor)->allows('restoreAny', Variant::class))->toBeFalse()
            ->and(Gate::forUser($actor)->allows('forceDeleteAny', Variant::class))->toBeFalse();
    });
});

it('fails closed for bulk policy checks when no owner context is resolved', function (): void {
    $actor = filamentGrowthPolicyUser();

    filamentGrowthBindNoOwner();
    $this->actingAs($actor);

    expect(Gate::forUser($actor)->allows('deleteAny', Experiment::class))->toBeFalse()
        ->and(Gate::forUser($actor)->allows('restoreAny', Experiment::class))->toBeFalse()
        ->and(Gate::forUser($actor)->allows('forceDeleteAny', Experiment::class))->toBeFalse()
        ->and(Gate::forUser($actor)->allows('deleteAny', Variant::class))->toBeFalse()
        ->and(Gate::forUser($actor)->allows('restoreAny', Variant::class))->toBeFalse()
        ->and(Gate::forUser($actor)->allows('forceDeleteAny', Variant::class))->toBeFalse();
});
