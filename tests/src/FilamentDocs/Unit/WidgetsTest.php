<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentDocs\Widgets\DocStatsWidget;
use AIArmada\FilamentDocs\Widgets\RecentDocumentsWidget;
use AIArmada\FilamentDocs\Widgets\RevenueChartWidget;
use AIArmada\FilamentDocs\Widgets\StatusBreakdownWidget;
use Filament\Tables\Table;

uses(TestCase::class);

function filamentDocs_invokeMethod(object $instance, string $methodName, array $arguments = []): mixed
{
    $method = new ReflectionMethod($instance, $methodName);
    $method->setAccessible(true);

    return $method->invokeArgs($instance, $arguments);
}

it('builds DocStatsWidget stats and formatting', function (): void {
    Doc::factory()->count(2)->create(['status' => DocStatus::DRAFT]);
    Doc::factory()->create(['status' => DocStatus::PAID, 'total' => 100, 'paid_at' => now()]);

    $widget = app(DocStatsWidget::class);

    $stats = filamentDocs_invokeMethod($widget, 'getStats');

    expect($stats)->toHaveCount(5);
    expect(filamentDocs_invokeMethod($widget, 'getColumns'))->toBe(5);
});

it('builds RecentDocumentsWidget table', function (): void {
    $widget = app(RecentDocumentsWidget::class);

    $table = $widget->table(Table::make($widget));

    expect($table->getColumns())->not()->toBeEmpty();
    expect($widget->getTableHeading())->toBe('Recent Documents');
});

it('builds RevenueChartWidget data, options, and type', function (): void {
    Doc::factory()->create([
        'status' => DocStatus::PAID,
        'paid_at' => now(),
        'total' => 100,
    ]);

    $widget = app(RevenueChartWidget::class);
    $data = filamentDocs_invokeMethod($widget, 'getData');

    expect($data['labels'])->toHaveCount(30);
    expect($data['datasets'][0]['data'])->toHaveCount(30);
    expect(filamentDocs_invokeMethod($widget, 'getType'))->toBe('line');
    expect(filamentDocs_invokeMethod($widget, 'getOptions'))->toBeArray();
});

it('builds StatusBreakdownWidget data and color mapping', function (): void {
    Doc::factory()->create(['status' => DocStatus::DRAFT]);
    Doc::factory()->create(['status' => DocStatus::PAID]);

    $widget = app(StatusBreakdownWidget::class);
    $data = filamentDocs_invokeMethod($widget, 'getData');

    expect($data['labels'])->not()->toBeEmpty();
    expect(filamentDocs_invokeMethod($widget, 'getType'))->toBe('doughnut');
    expect(filamentDocs_invokeMethod($widget, 'getOptions'))->toBeArray();
    expect(filamentDocs_invokeMethod($widget, 'getColorHex', ['unknown']))->toBe('#6b7280');
});
