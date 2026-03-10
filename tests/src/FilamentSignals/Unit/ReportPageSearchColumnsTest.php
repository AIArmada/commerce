<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\FilamentSignals\Pages\AcquisitionReport;
use AIArmada\FilamentSignals\Pages\ContentPerformanceReport;
use AIArmada\FilamentSignals\Pages\JourneyReport;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalSession;
use Filament\Tables\Table;

uses(FilamentSignalsTestCase::class);

it('searches acquisition campaign by the underlying campaign column', function (): void {
    $page = app(AcquisitionReport::class);
    $table = $page->table(Table::make($page));
    $column = $table->getColumn('acquisition_campaign');

    expect($column)->not()->toBeNull()
        ->and($column?->getSearchColumns(new SignalEvent))->toBe(['campaign']);
});

it('searches content path by the underlying path column', function (): void {
    $page = app(ContentPerformanceReport::class);
    $table = $page->table(Table::make($page));
    $column = $table->getColumn('content_path');

    expect($column)->not()->toBeNull()
        ->and($column?->getSearchColumns(new SignalEvent))->toBe(['path']);
});

it('searches journey paths by the underlying session columns', function (): void {
    $page = app(JourneyReport::class);
    $table = $page->table(Table::make($page));
    $entryColumn = $table->getColumn('journey_entry_path');
    $exitColumn = $table->getColumn('journey_exit_path');

    expect($entryColumn)->not()->toBeNull()
        ->and($entryColumn?->getSearchColumns(new SignalSession))->toBe(['entry_path'])
        ->and($exitColumn)->not()->toBeNull()
        ->and($exitColumn?->getSearchColumns(new SignalSession))->toBe(['exit_path']);
});
