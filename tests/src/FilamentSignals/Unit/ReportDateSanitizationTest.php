<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\FilamentSignals\Pages\ConversionFunnelReport;
use AIArmada\FilamentSignals\Pages\RetentionReport;
use AIArmada\FilamentSignals\Support\SignalsReportStateSanitizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

uses(FilamentSignalsTestCase::class);

function sanitizeRange(?string $from, ?string $to): array
{
    return app(SignalsReportStateSanitizer::class)->sanitizeDateRange($from, $to);
}

it('passes valid date ranges through untouched', function (): void {
    expect(sanitizeRange('2026-08-01', '2026-08-31'))
        ->toBe(['from' => '2026-08-01', 'to' => '2026-08-31']);
});

it('replaces malformed dates with the default range', function (): void {
    $range = sanitizeRange('not-a-date', '2026-13-45');

    expect($range['from'])->toMatch('/^\d{4}-\d{2}-\d{2}$/')
        ->and($range['to'])->toBe(CarbonImmutable::now()->toDateString());
});

it('orders reversed ranges instead of returning empty reports', function (): void {
    expect(sanitizeRange('2026-08-31', '2026-08-01'))
        ->toBe(['from' => '2026-08-01', 'to' => '2026-08-31']);
});

it('clamps ranges that exceed one year', function (): void {
    $range = sanitizeRange('2020-01-01', '2026-09-01');

    $span = CarbonImmutable::parse($range['from'])->diffInDays(CarbonImmutable::parse($range['to']));

    expect($range['to'])->toBe('2026-09-01')
        ->and($span)->toBeLessThan(SignalsReportStateSanitizer::MAX_DATE_RANGE_DAYS);
});

it('reuses a report within a render and recomputes after its date filter changes', function (string $pageClass, string $rowsMethod): void {
    $page = new $pageClass;
    $page->dateFrom = '2026-08-01';
    $page->dateTo = '2026-08-31';

    $connection = DB::connection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();

    try {
        $summary = $page->getSummary();
        $firstQueryCount = count($connection->getQueryLog());
        $page->$rowsMethod();

        expect($firstQueryCount)->toBeGreaterThan(0)
            ->and(count($connection->getQueryLog()))->toBe($firstQueryCount)
            ->and($page->getSummary())->toBe($summary);

        $page->dateFrom = '2026-09-01';
        $page->dateTo = '2026-09-30';
        $page->getSummary();

        expect(count($connection->getQueryLog()))->toBeGreaterThan($firstQueryCount);
    } finally {
        $connection->disableQueryLog();
    }
})->with([
    'conversion funnel' => [ConversionFunnelReport::class, 'getStages'],
    'retention' => [RetentionReport::class, 'getRows'],
]);
