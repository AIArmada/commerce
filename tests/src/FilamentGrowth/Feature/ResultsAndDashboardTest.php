<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentGrowth\Pages\ExperimentResultsPage;
use AIArmada\FilamentGrowth\Pages\GrowthDashboard;
use AIArmada\FilamentGrowth\Widgets\ExperimentWinnersWidget;
use AIArmada\FilamentGrowth\Widgets\GrowthStatsWidget;
use AIArmada\Growth\Actions\AggregateExperimentMetrics;
use AIArmada\Growth\Actions\BuildExperimentSignalProperties;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Str;

function filamentGrowthOwner(): User
{
    return User::query()->create([
        'name' => 'Filament Growth Owner ' . Str::random(6),
        'email' => 'filament-growth-' . Str::lower(Str::random(8)) . '@example.com',
        'password' => 'secret',
    ]);
}

function filamentGrowthExperiment(User $owner, string $currency = 'MYR'): array
{
    return OwnerContext::withOwner($owner, function () use ($currency): array {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Filament Growth Property ' . Str::random(6),
            'slug' => 'filament-growth-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => $currency,
            'is_active' => true,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'module_type' => 'sales_page_test',
            'status' => 'active',
        ]);

        /** @var Variant $variantA */
        $variantA = Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'A',
            'name' => 'Control',
            'traffic_percentage' => 50,
            'position' => 1,
            'is_control' => true,
        ]);

        /** @var Variant $variantB */
        $variantB = Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'B',
            'name' => 'Challenger',
            'traffic_percentage' => 50,
            'position' => 2,
        ]);

        $assignmentA = Assignment::query()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variantA->getKey(),
            'subject_key' => 'anonymous:filament-a',
            'bucket' => 0,
            'assigned_at' => CarbonImmutable::now(),
            'first_exposed_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);

        $assignmentB = Assignment::query()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variantB->getKey(),
            'subject_key' => 'anonymous:filament-b',
            'bucket' => 1,
            'assigned_at' => CarbonImmutable::now(),
            'first_exposed_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);

        SignalEvent::query()->create([
            'tracked_property_id' => $experiment->tracked_property_id,
            'occurred_at' => CarbonImmutable::now(),
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
            'revenue_minor' => 35000,
            'currency' => $currency,
            'properties' => app(BuildExperimentSignalProperties::class)->handle($assignmentA),
        ]);

        SignalEvent::query()->create([
            'tracked_property_id' => $experiment->tracked_property_id,
            'occurred_at' => CarbonImmutable::now(),
            'event_name' => 'checkout.started',
            'event_category' => 'checkout',
            'revenue_minor' => 0,
            'currency' => $currency,
            'properties' => app(BuildExperimentSignalProperties::class)->handle($assignmentB),
        ]);

        return [$experiment, $variantA, $variantB];
    });
}

function filamentGrowthPendingExperiment(User $owner): Experiment
{
    return OwnerContext::withOwner($owner, function (): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Filament Growth Pending Property ' . Str::random(6),
            'slug' => 'filament-growth-pending-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'module_type' => 'sales_page_test',
            'status' => 'active',
        ]);

        Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'A',
            'name' => 'Control',
            'traffic_percentage' => 100,
            'position' => 1,
            'is_control' => true,
        ]);

        return $experiment;
    });
}

function filamentGrowthAssignedWithoutOutcomeExperiment(User $owner): Experiment
{
    return OwnerContext::withOwner($owner, function (): Experiment {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => 'Filament Growth Assigned Property ' . Str::random(6),
            'slug' => 'filament-growth-assigned-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => 'MYR',
            'is_active' => true,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'module_type' => 'sales_page_test',
            'status' => 'active',
        ]);

        /** @var Variant $variant */
        $variant = Variant::factory()->create([
            'experiment_id' => $experiment->getKey(),
            'code' => 'A',
            'name' => 'Control',
            'traffic_percentage' => 100,
            'position' => 1,
            'is_control' => true,
        ]);

        Assignment::query()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variant->getKey(),
            'subject_key' => 'anonymous:assigned-no-outcome',
            'bucket' => 0,
            'assigned_at' => CarbonImmutable::now(),
            'first_exposed_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);

        return $experiment;
    });
}

function filamentGrowthGlobalExperiment(string $currency = 'USD', ?string $propertyName = null): array
{
    return OwnerContext::withOwner(null, function () use ($currency, $propertyName): array {
        $trackedProperty = TrackedProperty::query()->create([
            'name' => $propertyName ?? 'Filament Growth Global Property ' . Str::random(6),
            'slug' => 'filament-growth-global-' . Str::lower(Str::random(8)),
            'write_key' => Str::random(40),
            'type' => 'website',
            'timezone' => 'UTC',
            'currency' => $currency,
            'is_active' => true,
            'owner_type' => null,
            'owner_id' => null,
        ]);

        /** @var Experiment $experiment */
        $experiment = Experiment::factory()->global()->create([
            'tracked_property_id' => $trackedProperty->getKey(),
            'module_type' => 'sales_page_test',
            'status' => 'active',
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

        /** @var Assignment $assignment */
        $assignment = Assignment::factory()->global()->create([
            'experiment_id' => $experiment->getKey(),
            'variant_id' => $variant->getKey(),
            'subject_key' => 'anonymous:filament-global',
            'bucket' => 0,
            'assigned_at' => CarbonImmutable::now(),
            'first_exposed_at' => CarbonImmutable::now(),
            'last_seen_at' => CarbonImmutable::now(),
        ]);

        SignalEvent::query()->create([
            'tracked_property_id' => $experiment->tracked_property_id,
            'occurred_at' => CarbonImmutable::now(),
            'event_name' => 'order.paid',
            'event_category' => 'conversion',
            'revenue_minor' => 47000,
            'currency' => $currency,
            'properties' => app(BuildExperimentSignalProperties::class)->handle($assignment),
            'owner_type' => null,
            'owner_id' => null,
        ]);

        return [$experiment, $trackedProperty];
    });
}

function filamentGrowthBindFailingAggregator(string $message = 'Aggregation failed'): void
{
    app()->bind(AggregateExperimentMetrics::class, fn () => new class($message)
    {
        public function __construct(private readonly string $message) {}

        public function handle(Experiment $experiment): array
        {
            throw new RuntimeException($this->message);
        }
    });
}

it('loads winner summaries and variant comparison data on the results page', function (): void {
    $owner = filamentGrowthOwner();
    [$experiment, $variantA] = filamentGrowthExperiment($owner);

    $page = app(ExperimentResultsPage::class);

    OwnerContext::withOwner($owner, function () use ($experiment, $page, $variantA): void {
        $page->experimentId = (string) $experiment->getKey();
        $page->chartMetric = 'revenue_per_visitor';
        $page->loadResults();

        $results = $page->getResults();
        $winnerSummary = $page->getWinnerSummary();
        $variantComparison = $page->getVariantComparison();

        expect($results['experiment_id'])->toBe((string) $experiment->getKey())
            ->and($results['currency'])->toBe('MYR')
            ->and($winnerSummary)->not->toBeNull()
            ->and($winnerSummary['variant_id'])->toBe((string) $variantA->getKey())
            ->and($variantComparison)->toHaveCount(2);
    });
});

it('fails softly on the results page when metric aggregation throws', function (): void {
    $owner = filamentGrowthOwner();
    [$experiment] = filamentGrowthExperiment($owner);
    $page = app(ExperimentResultsPage::class);

    filamentGrowthBindFailingAggregator('results aggregation failure');

    OwnerContext::withOwner($owner, function () use ($experiment, $page): void {
        $page->experimentId = (string) $experiment->getKey();
        $page->loadResults();

        expect($page->getResults())->toBe([])
            ->and($page->getWinnerSummary())->toBeNull()
            ->and($page->getVariantComparison())->toBe([]);
    });
});

it('does not expose derived analytics as mutable public properties on the results page', function (): void {
    $publicPropertyNames = collect((new ReflectionClass(ExperimentResultsPage::class))->getProperties(ReflectionProperty::IS_PUBLIC))
        ->map(static fn (ReflectionProperty $property): string => $property->getName())
        ->all();

    expect($publicPropertyNames)->not->toContain('results')
        ->and($publicPropertyNames)->not->toContain('winnerSummary')
        ->and($publicPropertyNames)->not->toContain('variantComparison');
});

it('keeps the winner metric label separate from the selected chart metric', function (): void {
    $owner = filamentGrowthOwner();
    [$experiment] = filamentGrowthExperiment($owner);

    $page = app(ExperimentResultsPage::class);

    OwnerContext::withOwner($owner, function () use ($experiment, $page): void {
        $page->experimentId = (string) $experiment->getKey();
        $page->chartMetric = 'purchases';
        $page->loadResults();

        $viewData = $page->getViewData();

        expect($viewData['winnerMetricLabel'])->toBe('Revenue Per Visitor')
            ->and($viewData['chartMetricLabel'])->toBe('Purchases');
    });
});

it('formats result money using the selected experiment currency', function (): void {
    $owner = filamentGrowthOwner();
    [$experiment] = filamentGrowthExperiment($owner, 'USD');

    $page = app(ExperimentResultsPage::class);

    OwnerContext::withOwner($owner, function () use ($experiment, $page): void {
        $page->experimentId = (string) $experiment->getKey();
        $page->loadResults();

        expect($page->formatMoney(12345))->toBe(money(12345, 'USD', false)->format());
    });
});

it('shows mixed revenue totals on the dashboard when experiments use different currencies', function (): void {
    $owner = filamentGrowthOwner();
    filamentGrowthExperiment($owner, 'MYR');
    filamentGrowthExperiment($owner, 'USD');

    $widget = app(GrowthStatsWidget::class);
    $method = new ReflectionMethod(GrowthStatsWidget::class, 'getStats');
    $method->setAccessible(true);

    OwnerContext::withOwner($owner, function () use ($method, $widget): void {
        /** @var array<int, Stat> $stats */
        $stats = $method->invoke($widget);

        expect($stats[3]->getValue())->toBe('Mixed')
            ->and((string) $stats[3]->getDescription())->toContain(money(35000, 'MYR', false)->format())
            ->and((string) $stats[3]->getDescription())->toContain(money(35000, 'USD', false)->format());
    });
});

it('skips broken metric aggregation rows when rendering growth widgets', function (): void {
    $owner = filamentGrowthOwner();
    filamentGrowthExperiment($owner);

    filamentGrowthBindFailingAggregator('widget aggregation failure');

    $statsWidget = app(GrowthStatsWidget::class);
    $statsMethod = new ReflectionMethod(GrowthStatsWidget::class, 'getStats');
    $statsMethod->setAccessible(true);
    $winnersWidget = app(ExperimentWinnersWidget::class);

    OwnerContext::withOwner($owner, function () use ($statsMethod, $statsWidget, $winnersWidget): void {
        /** @var array<int, Stat> $stats */
        $stats = $statsMethod->invoke($statsWidget);

        expect($stats[0]->getValue())->toBe('1')
            ->and($stats[3]->getValue())->toBe(money(0, 'MYR', false)->format())
            ->and((string) $stats[3]->getDescription())->toContain('0 experiment(s) with winner-ready metrics')
            ->and($winnersWidget->getExperimentSnapshots())->toBe([]);
    });
});

it('limits dashboard experiment aggregation using filament-growth table config', function (): void {
    config()->set('filament-growth.tables.stats_experiment_limit', 1);

    $owner = filamentGrowthOwner();
    filamentGrowthExperiment($owner, 'MYR');
    filamentGrowthExperiment($owner, 'USD');

    $widget = app(GrowthStatsWidget::class);
    $method = new ReflectionMethod(GrowthStatsWidget::class, 'getStats');
    $method->setAccessible(true);

    OwnerContext::withOwner($owner, function () use ($method, $widget): void {
        /** @var array<int, Stat> $stats */
        $stats = $method->invoke($widget);

        expect($stats[3]->getValue())->not->toBe('Mixed');
    });
});

it('keeps exact active experiment counts while clearly sampling dashboard revenue metrics', function (): void {
    config()->set('filament-growth.tables.stats_experiment_limit', 1);

    $owner = filamentGrowthOwner();
    filamentGrowthExperiment($owner, 'MYR');
    filamentGrowthExperiment($owner, 'USD');

    $widget = app(GrowthStatsWidget::class);
    $method = new ReflectionMethod(GrowthStatsWidget::class, 'getStats');
    $method->setAccessible(true);

    OwnerContext::withOwner($owner, function () use ($method, $widget): void {
        /** @var array<int, Stat> $stats */
        $stats = $method->invoke($widget);

        expect($stats[0]->getValue())->toBe('2')
            ->and((string) $stats[3]->getDescription())->toContain('Based on latest 1 of 2 experiments');
    });
});

it('registers the winners widget on the growth dashboard and builds experiment snapshots', function (): void {
    $owner = filamentGrowthOwner();
    [$experiment] = filamentGrowthExperiment($owner);

    $dashboard = app(GrowthDashboard::class);

    expect($dashboard->getWidgets())
        ->toContain(GrowthStatsWidget::class)
        ->toContain(ExperimentWinnersWidget::class);

    $widget = app(ExperimentWinnersWidget::class);

    OwnerContext::withOwner($owner, function () use ($widget): void {
        $snapshots = $widget->getExperimentSnapshots();

        expect($snapshots)->toHaveCount(1)
            ->and($snapshots[0]['winner_name'])->toBe('Control')
            ->and($snapshots[0]['module_type'])->toBe('Sales Page Test')
            ->and($snapshots[0]['results_url'])->toContain('/admin/growth/results');
    });

    OwnerContext::withOwner($owner, function () use ($experiment): void {
        expect(ExperimentResultsPage::getUrl(['experiment' => $experiment->getKey()]))->toContain('/admin/growth/results');
    });
});

it('shows a pending winner state when an experiment has no assignments yet', function (): void {
    $owner = filamentGrowthOwner();
    $experiment = filamentGrowthPendingExperiment($owner);
    $page = app(ExperimentResultsPage::class);
    $widget = app(ExperimentWinnersWidget::class);

    OwnerContext::withOwner($owner, function () use ($experiment, $page, $widget): void {
        $page->experimentId = (string) $experiment->getKey();
        $page->loadResults();

        expect($page->getWinnerSummary())->toBeNull();

        $snapshots = $widget->getExperimentSnapshots();

        expect($snapshots)->toHaveCount(1)
            ->and($snapshots[0]['winner_name'])->toBe('Pending')
            ->and($snapshots[0]['winner_metric_value'])->toBe('—');
    });
});

it('keeps the winner pending when assignments exist without qualifying outcome data', function (): void {
    $owner = filamentGrowthOwner();
    $experiment = filamentGrowthAssignedWithoutOutcomeExperiment($owner);
    $page = app(ExperimentResultsPage::class);
    $widget = app(ExperimentWinnersWidget::class);

    OwnerContext::withOwner($owner, function () use ($experiment, $page, $widget): void {
        $page->experimentId = (string) $experiment->getKey();
        $page->loadResults();

        expect($page->getWinnerSummary())->toBeNull();

        $snapshots = $widget->getExperimentSnapshots();

        expect($snapshots)->toHaveCount(1)
            ->and($snapshots[0]['winner_name'])->toBe('Pending')
            ->and($snapshots[0]['winner_metric_value'])->toBe('—');
    });
});

it('removes experiment management actions when the experiments resource feature is disabled', function (): void {
    config()->set('filament-growth.features.experiments', false);

    $page = app(ExperimentResultsPage::class);
    $method = new ReflectionMethod(ExperimentResultsPage::class, 'getHeaderActions');
    $method->setAccessible(true);

    /** @var array<int, Action> $actions */
    $actions = $method->invoke($page);

    expect(array_map(fn (Action $action): string => $action->getName(), $actions))
        ->toBe(['refreshResults']);
});

it('falls back to inert results links when the results feature is disabled', function (): void {
    config()->set('filament-growth.features.results', false);

    $owner = filamentGrowthOwner();
    filamentGrowthExperiment($owner);
    $widget = app(ExperimentWinnersWidget::class);

    OwnerContext::withOwner($owner, function () use ($widget): void {
        $snapshots = $widget->getExperimentSnapshots();

        expect($snapshots)->toHaveCount(1)
            ->and($snapshots[0]['results_url'])->toBe('#');
    });
});

it('normalizes malformed experiment query values before selecting the active results experiment', function (): void {
    $owner = filamentGrowthOwner();
    [$experiment] = filamentGrowthExperiment($owner);

    $page = app(ExperimentResultsPage::class);
    $method = new ReflectionMethod(ExperimentResultsPage::class, 'normalizeRequestedExperimentId');
    $method->setAccessible(true);

    OwnerContext::withOwner($owner, function () use ($experiment, $method, $page): void {
        expect($method->invoke($page, ['invalid'], (string) $experiment->getKey()))
            ->toBe((string) $experiment->getKey())
            ->and($method->invoke($page, '', (string) $experiment->getKey()))
            ->toBe((string) $experiment->getKey())
            ->and($method->invoke($page, (string) $experiment->getKey(), null))
            ->toBe((string) $experiment->getKey());
    });
});

it('normalizes malformed chart metrics before building results output', function (): void {
    $owner = filamentGrowthOwner();
    [$experiment] = filamentGrowthExperiment($owner);

    $page = app(ExperimentResultsPage::class);

    OwnerContext::withOwner($owner, function () use ($experiment, $page): void {
        $page->experimentId = (string) $experiment->getKey();
        $page->chartMetric = ['invalid'];
        $page->loadResults();

        $viewData = $page->getViewData();

        expect($page->chartMetric)->toBe('revenue_per_visitor')
            ->and($viewData['chartMetricLabel'])->toBe('Revenue Per Visitor');
    });
});

it('resolves readable global experiment tracked properties on the results page from tenant context', function (): void {
    config()->set('growth.features.owner.include_global', true);
    config()->set('signals.owner.include_global', false);

    $owner = filamentGrowthOwner();
    [$experiment, $trackedProperty] = filamentGrowthGlobalExperiment('USD', 'Filament Growth Global Results Property');
    $page = app(ExperimentResultsPage::class);

    OwnerContext::withOwner($owner, function () use ($experiment, $page, $trackedProperty): void {
        $page->experimentId = (string) $experiment->getKey();
        $page->loadResults();

        $selectedExperiment = $page->selectedExperiment();
        $results = $page->getResults();

        expect($results['currency'])->toBe('USD')
            ->and($selectedExperiment)->toBeInstanceOf(Experiment::class)
            ->and($selectedExperiment->trackedProperty?->name)->toBe((string) $trackedProperty->name);
    });
});

it('does not resolve foreign tracked properties on the results page when growth owner scoping is disabled but signals owner scoping remains enabled', function (): void {
    config()->set('signals.owner.enabled', true);

    $ownerA = filamentGrowthOwner();
    $ownerB = filamentGrowthOwner();
    [$experiment] = filamentGrowthExperiment($ownerA, 'USD');
    $page = app(ExperimentResultsPage::class);

    OwnerContext::withOwner($ownerB, function () use ($experiment, $page): void {
        $page->experimentId = (string) $experiment->getKey();
        $page->loadResults();

        $selectedExperiment = $page->selectedExperiment();
        $results = $page->getResults();

        expect($selectedExperiment)->toBeNull()
            ->and($results)->toBe([]);
    });
});

it('does not show dashboard experiment data for foreign tracked properties when growth owner scoping is disabled but signals owner scoping remains enabled', function (): void {
    config()->set('signals.owner.enabled', true);

    $ownerA = filamentGrowthOwner();
    $ownerB = filamentGrowthOwner();
    filamentGrowthExperiment($ownerA, 'USD');

    $statsWidget = app(GrowthStatsWidget::class);
    $statsMethod = new ReflectionMethod(GrowthStatsWidget::class, 'getStats');
    $statsMethod->setAccessible(true);
    $winnersWidget = app(ExperimentWinnersWidget::class);

    OwnerContext::withOwner($ownerB, function () use ($statsMethod, $statsWidget, $winnersWidget): void {
        /** @var array<int, Stat> $stats */
        $stats = $statsMethod->invoke($statsWidget);

        expect($stats[0]->getValue())->toBe('0')
            ->and($stats[1]->getValue())->toBe('0')
            ->and($stats[2]->getValue())->toBe('0')
            ->and($winnersWidget->getExperimentSnapshots())->toBe([]);
    });
});

it('gates dashboard and results page access when only foreign tracked properties exist', function (): void {
    config()->set('signals.owner.enabled', true);

    $ownerA = filamentGrowthOwner();
    $ownerB = filamentGrowthOwner();
    filamentGrowthExperiment($ownerA, 'USD');

    OwnerContext::withOwner($ownerB, function (): void {
        expect(GrowthDashboard::canAccess())->toBeFalse()
            ->and(ExperimentResultsPage::canAccess())->toBeFalse();
    });
});

it('clears the selected experiment when the requested experiment is no longer accessible', function (): void {
    config()->set('signals.owner.enabled', true);

    $ownerA = filamentGrowthOwner();
    $ownerB = filamentGrowthOwner();
    [$foreignExperiment] = filamentGrowthExperiment($ownerA, 'USD');
    [$accessibleExperiment] = filamentGrowthExperiment($ownerB, 'MYR');
    $page = app(ExperimentResultsPage::class);

    OwnerContext::withOwner($ownerB, function () use ($accessibleExperiment, $foreignExperiment, $page): void {
        $page->experimentId = (string) $foreignExperiment->getKey();
        $page->loadResults();

        $selectedExperiment = $page->selectedExperiment();

        expect($accessibleExperiment)->toBeInstanceOf(Experiment::class)
            ->and($selectedExperiment)->toBeNull()
            ->and($page->experimentId)->toBeNull()
            ->and($page->getResults())->toBe([]);
    });
});
