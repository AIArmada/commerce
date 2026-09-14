<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentGrowth\Pages\ExperimentResultsPage;
use AIArmada\FilamentGrowth\Pages\ManageGrowthSettings;
use AIArmada\FilamentGrowth\Resources\VariantResource\Tables\VariantsTable;
use AIArmada\FilamentGrowth\Support\ExperimentHelpers;
use AIArmada\FilamentGrowth\Widgets\ExperimentWinnersWidget;
use AIArmada\Growth\Actions\AggregateExperimentMetrics;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\TrackedProperty;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

class RoundTwoGrowthSchemaHost extends LivewireComponent implements HasSchemas
{
    use InteractsWithSchemas;

    public ?array $data = [];

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function render(): string
    {
        return '';
    }
}

function r2GrowthUser(string $prefix = 'r2-growth'): User
{
    return User::query()->create([
        'name' => 'Round Two Growth',
        'email' => $prefix . '-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function r2GrowthBindOwner(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

function r2GrowthProperty(User $owner, string $name = 'R2 Property'): TrackedProperty
{
    return OwnerContext::withOwner($owner, function () use ($name): TrackedProperty {
        return TrackedProperty::query()->create([
            'name' => $name . ' ' . Str::random(6),
            'slug' => 'r2-growth-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);
    });
}

function r2GrowthExperiment(User $owner, TrackedProperty $property, string $name = 'R2 Experiment'): Experiment
{
    return OwnerContext::withOwner($owner, function () use ($property, $name): Experiment {
        return Experiment::factory()->create([
            'tracked_property_id' => $property->getKey(),
            'name' => $name,
            'module_type' => 'sales_page_test',
            'status' => 'active',
        ]);
    });
}

function r2GrowthVariant(Experiment $experiment, string $code = 'A'): Variant
{
    return Variant::factory()->create([
        'experiment_id' => $experiment->getKey(),
        'code' => $code,
        'name' => 'Variant ' . $code,
        'traffic_percentage' => 100,
        'position' => 1,
        'is_control' => true,
    ]);
}

function r2GrowthFindComponent(array $components, string $name): ?Component
{
    foreach ($components as $component) {
        if (! $component instanceof Component) {
            continue;
        }

        if (method_exists($component, 'getName') && $component->getName() === $name) {
            return $component;
        }

        if (method_exists($component, 'getChildComponents')) {
            $found = r2GrowthFindComponent($component->getChildComponents(), $name);

            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

function r2GrowthMetricsStub(string $experimentId, string $winnerName = 'Control'): array
{
    return [
        'experiment_id' => $experimentId,
        'currency' => 'MYR',
        'winner_metric' => 'revenue_per_visitor',
        'truncated' => false,
        'variants' => [
            [
                'variant_id' => 'variant-a',
                'code' => 'A',
                'name' => $winnerName,
                'revenue_per_visitor' => 120,
            ],
        ],
        'winner_variant_id' => 'variant-a',
        'totals' => ['revenue_minor' => 12000, 'assignments' => 100],
    ];
}

it('denies growth settings access without the settings permission', function (): void {
    config()->set('growth.features.owner.enabled', false);

    test()->actingAs(r2GrowthUser());

    expect(ManageGrowthSettings::canAccess())->toBeFalse();
});

it('allows growth settings access with the settings permission', function (): void {
    config()->set('growth.features.owner.enabled', false);
    Gate::define('growth.settings.manage', fn (): bool => true);

    test()->actingAs(r2GrowthUser());

    expect(ManageGrowthSettings::canAccess())->toBeTrue();
});

it('does not grant growth settings access through experiment viewing', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $owner = r2GrowthUser();
    $actor = r2GrowthUser();

    $property = r2GrowthProperty($owner);
    r2GrowthExperiment($owner, $property, 'Settings Gate Experiment');

    r2GrowthBindOwner($owner);
    test()->actingAs($actor);

    OwnerContext::withOwner($owner, function () use ($actor): void {
        expect(Gate::forUser($actor)->allows('viewAny', Experiment::class))->toBeTrue()
            ->and(ManageGrowthSettings::canAccess())->toBeFalse();
    });
});

it('batches winner snapshots through a single aggregation call', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $owner = r2GrowthUser();
    $property = r2GrowthProperty($owner);

    $experiments = [
        r2GrowthExperiment($owner, $property, 'Batch One'),
        r2GrowthExperiment($owner, $property, 'Batch Two'),
        r2GrowthExperiment($owner, $property, 'Batch Three'),
    ];

    $calls = 0;

    $fake = new class($calls)
    {
        /** @var int */
        public $callsRef;

        public function __construct(int &$calls)
        {
            $this->callsRef = &$calls;
        }

        public function handleMany(EloquentCollection $experiments): array
        {
            $this->callsRef++;

            $results = [];

            foreach ($experiments as $experiment) {
                $results[(string) $experiment->getKey()] = r2GrowthMetricsStub((string) $experiment->getKey());
            }

            return ['results' => $results, 'variant_count' => 0, 'assignment_count' => 0];
        }
    };

    app()->bind(AggregateExperimentMetrics::class, fn () => $fake);

    $snapshots = app(ExperimentWinnersWidget::class)->getExperimentSnapshots();

    expect($calls)->toBe(1)
        ->and($snapshots)->toHaveCount(3)
        ->and(collect($snapshots)->pluck('name')->sort()->values()->all())
        ->toEqual(['Batch One', 'Batch Three', 'Batch Two']);
});

it('returns no winner snapshots when no experiments exist', function (): void {
    config()->set('growth.features.owner.enabled', false);

    expect(app(ExperimentWinnersWidget::class)->getExperimentSnapshots())->toBe([]);
});

it('caps experiment search results instead of loading every experiment', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $owner = r2GrowthUser();
    $property = r2GrowthProperty($owner);

    for ($i = 0; $i < 60; $i++) {
        r2GrowthExperiment($owner, $property, 'Cap Test ' . $i);
    }

    $method = new ReflectionMethod(ExperimentResultsPage::class, 'searchExperimentOptions');
    $results = $method->invoke(app(ExperimentResultsPage::class), 'Cap Test');

    expect($results)->toHaveCount(50);
});

it('builds the results experiment select with lazy search and no preload query', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $page = app(ExperimentResultsPage::class);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $schema = $page->form(Schema::make(new RoundTwoGrowthSchemaHost));

    $queries = DB::getQueryLog();

    DB::disableQueryLog();

    $select = r2GrowthFindComponent($schema->getComponents(), 'experimentId');

    expect($select)->toBeInstanceOf(Select::class)
        ->and($select->hasDynamicSearchResults())->toBeTrue()
        ->and($queries)->toBeEmpty();
});

it('resolves experiment option labels within the current owner scope', function (): void {
    config()->set('growth.features.owner.enabled', true);
    config()->set('growth.features.owner.include_global', false);

    $ownerA = r2GrowthUser();
    $ownerB = r2GrowthUser();

    $experimentA = r2GrowthExperiment($ownerA, r2GrowthProperty($ownerA), 'Label Mine');
    $experimentB = r2GrowthExperiment($ownerB, r2GrowthProperty($ownerB), 'Label Foreign');

    r2GrowthBindOwner($ownerA);

    $method = new ReflectionMethod(ExperimentResultsPage::class, 'experimentOptionLabel');
    $page = app(ExperimentResultsPage::class);

    expect($method->invoke($page, (string) $experimentA->getKey()))->toBe('Label Mine')
        ->and($method->invoke($page, (string) $experimentB->getKey()))->toBeNull();
});

it('drops cross-owner experiment ids on the results page', function (): void {
    config()->set('growth.features.owner.enabled', true);
    config()->set('growth.features.owner.include_global', false);

    $ownerA = r2GrowthUser();
    $ownerB = r2GrowthUser();

    $experimentA = r2GrowthExperiment($ownerA, r2GrowthProperty($ownerA), 'Scope Mine');
    $experimentB = r2GrowthExperiment($ownerB, r2GrowthProperty($ownerB), 'Scope Foreign');

    r2GrowthBindOwner($ownerA);

    $page = app(ExperimentResultsPage::class);
    $page->experimentId = (string) $experimentB->getKey();
    $page->loadResults();

    expect($page->experimentId)->toBeNull();

    $page = app(ExperimentResultsPage::class);
    $page->experimentId = (string) $experimentA->getKey();
    $page->loadResults();

    expect($page->experimentId)->toBe((string) $experimentA->getKey())
        ->and($page->selectedExperiment()?->getKey())->toBe((string) $experimentA->getKey());
});

it('scopes winner snapshots to the current owner', function (): void {
    config()->set('growth.features.owner.enabled', true);
    config()->set('growth.features.owner.include_global', false);

    $ownerA = r2GrowthUser();
    $ownerB = r2GrowthUser();

    r2GrowthExperiment($ownerA, r2GrowthProperty($ownerA), 'Snapshot Mine');
    r2GrowthExperiment($ownerB, r2GrowthProperty($ownerB), 'Snapshot Foreign');

    r2GrowthBindOwner($ownerA);

    $fake = new class
    {
        public function handleMany(EloquentCollection $experiments): array
        {
            $results = [];

            foreach ($experiments as $experiment) {
                $results[(string) $experiment->getKey()] = r2GrowthMetricsStub((string) $experiment->getKey());
            }

            return ['results' => $results, 'variant_count' => 0, 'assignment_count' => 0];
        }
    };

    app()->bind(AggregateExperimentMetrics::class, fn () => $fake);

    $snapshots = app(ExperimentWinnersWidget::class)->getExperimentSnapshots();

    expect($snapshots)->toHaveCount(1)
        ->and($snapshots[0]['name'])->toBe('Snapshot Mine');
});

it('hides bulk experiment delete when no experiment is writable', function (): void {
    config()->set('growth.features.owner.enabled', false);

    test()->actingAs(r2GrowthUser());

    expect(Experiment::query()->count())->toBe(0)
        ->and(ExperimentHelpers::canDeleteAnyExperiment())->toBeFalse()
        ->and(Gate::allows('deleteAny', Experiment::class))->toBeFalse();
});

it('shows bulk experiment delete when a writable experiment exists', function (): void {
    config()->set('growth.features.owner.enabled', false);

    $owner = r2GrowthUser();
    $actor = r2GrowthUser();

    r2GrowthExperiment($owner, r2GrowthProperty($owner), 'Deletable Experiment');

    r2GrowthBindOwner($owner);
    test()->actingAs($actor);

    OwnerContext::withOwner($owner, function (): void {
        expect(ExperimentHelpers::canDeleteAnyExperiment())->toBeTrue()
            ->and(Gate::allows('deleteAny', Experiment::class))->toBeTrue();
    });
});

it('uses the loaded experiment relation for variant row names', function (): void {
    $owner = r2GrowthUser();

    r2GrowthBindOwner($owner);

    $experiment = r2GrowthExperiment($owner, r2GrowthProperty($owner), 'Row Name Experiment');
    $variant = OwnerContext::withOwner($owner, fn (): Variant => r2GrowthVariant($experiment));
    $variant->load('experiment');

    $method = new ReflectionMethod(VariantsTable::class, 'experimentName');

    DB::enableQueryLog();
    DB::flushQueryLog();

    $name = $method->invoke(null, $variant);
    $queries = DB::getQueryLog();

    DB::disableQueryLog();

    expect($name)->toBe('Row Name Experiment')
        ->and($queries)->toBeEmpty()
        ->and($method->invoke(null, $variant->fresh()))->toBe('Row Name Experiment');
});
