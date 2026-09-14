<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Widgets\RedemptionTrendChart;

uses(TestCase::class);

function trendPointCount(?string $filter): int
{
    $widget = app(RedemptionTrendChart::class);
    $widget->filter = $filter;

    $data = (new ReflectionMethod(RedemptionTrendChart::class, 'getData'))->invoke($widget);

    return count($data['labels']);
}

it('clamps forged trend filters to the default range', function (): void {
    expect(trendPointCount('9999999'))->toBe(31)
        ->and(trendPointCount('bogus'))->toBe(31)
        ->and(trendPointCount(null))->toBe(31);
});

it('honors the offered trend ranges', function (): void {
    expect(trendPointCount('7'))->toBe(8)
        ->and(trendPointCount('90'))->toBe(91);
});
