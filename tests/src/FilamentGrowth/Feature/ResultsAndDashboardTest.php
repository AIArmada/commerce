<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentGrowth\Pages\ExperimentResultsPage;
use AIArmada\FilamentGrowth\Pages\GrowthDashboard;
use AIArmada\FilamentGrowth\Widgets\ExperimentWinnersWidget;
use AIArmada\FilamentGrowth\Widgets\GrowthStatsWidget;
use AIArmada\Growth\Actions\BuildExperimentSignalProperties;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
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
    return OwnerContext::withOwner($owner, function () use ($currency, $owner): array {
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

it('loads winner summaries and variant comparison data on the results page', function (): void {
    $owner = filamentGrowthOwner();
    [$experiment, $variantA] = filamentGrowthExperiment($owner);

    $page = app(ExperimentResultsPage::class);

    OwnerContext::withOwner($owner, function () use ($experiment, $page, $variantA): void {
        $page->experimentId = (string) $experiment->getKey();
        $page->chartMetric = 'revenue_per_visitor';
        $page->loadResults();

        expect($page->results['experiment_id'])->toBe((string) $experiment->getKey())
            ->and($page->results['currency'])->toBe('MYR')
            ->and($page->winnerSummary)->not->toBeNull()
            ->and($page->winnerSummary['variant_id'])->toBe((string) $variantA->getKey())
            ->and($page->variantComparison)->toHaveCount(2);
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
    $method = new \ReflectionMethod(GrowthStatsWidget::class, 'getStats');
    $method->setAccessible(true);

    OwnerContext::withOwner($owner, function () use ($method, $widget): void {
        /** @var array<int, \Filament\Widgets\StatsOverviewWidget\Stat> $stats */
        $stats = $method->invoke($widget);

        expect($stats[3]->getValue())->toBe('Mixed')
            ->and((string) $stats[3]->getDescription())->toContain(money(35000, 'MYR', false)->format())
            ->and((string) $stats[3]->getDescription())->toContain(money(35000, 'USD', false)->format());
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